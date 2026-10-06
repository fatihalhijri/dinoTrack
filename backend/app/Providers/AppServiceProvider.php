<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\MessageSender;
use App\Contracts\NetworkController;
use App\Contracts\PaymentGateway;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentCharge;
use App\Models\PaymentNotification;
use App\Models\Router;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Messaging\FonnteMessageSender;
use App\Services\Messaging\LogMessageSender;
use App\Services\Network\MikrotikNetworkController;
use App\Services\Payment\MidtransPaymentGateway;
use App\Support\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public const int WEBHOOK_RATE_LIMIT_PER_MINUTE = 120;

    public const int ISOLATION_PAGE_VIEWS_PER_MINUTE = 120;

    public const int ISOLATION_LOOKUPS_PER_MINUTE = 20;

    public const int ISOLATION_LOOKUPS_PER_CODE_PER_HOUR = 10;

    public const int PUBLIC_INVOICE_VIEWS_PER_MINUTE = 120;

    public const int PUBLIC_INVOICE_PAYMENTS_PER_MINUTE = 10;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SettingsRepository::class);
        $this->app->bind(PaymentGateway::class, MidtransPaymentGateway::class);
        $this->app->bind(NetworkController::class, MikrotikNetworkController::class);
        $this->app->bind(MessageSender::class, fn (): MessageSender => match (config('services.whatsapp.driver')) {
            'fonnte' => $this->app->make(FonnteMessageSender::class),
            'log' => $this->app->make(LogMessageSender::class),
            default => throw new InvalidArgumentException('Driver WhatsApp tidak dikenal: '.config('services.whatsapp.driver')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Webhook dibatasi per IP. Melebihi batas dibalas 429 dan Midtrans mencoba ulang nanti.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(self::WEBHOOK_RATE_LIMIT_PER_MINUTE)->by($request->ip()));
        RateLimiter::for('isolation-page', $this->isolationPageLimits(...));
        RateLimiter::for('public-invoice', fn (Request $request): Limit => Limit::perMinute(self::PUBLIC_INVOICE_VIEWS_PER_MINUTE)->by($request->ip()));
        // Path memuat ID invoice; throttle berjalan sebelum route model binding.
        RateLimiter::for('public-invoice-pay', fn (Request $request): Limit => Limit::perMinute(self::PUBLIC_INVOICE_PAYMENTS_PER_MINUTE)
            ->by($request->ip().'|'.$request->path()));
        RateLimiter::for('whatsapp', $this->whatsappLimit(...));
    }

    /**
     * Satu nomor pengirim untuk seluruh aplikasi, jadi batasnya global (bukan per pelanggan):
     * paling banyak satu pesan per `seconds_per_message` detik agar nomor tidak diblokir WhatsApp.
     */
    protected function whatsappLimit(): Limit
    {
        $seconds = (int) config('services.whatsapp.seconds_per_message');

        return $seconds > 0 ? Limit::perSecond(1, $seconds)->by('whatsapp-sender') : Limit::none();
    }

    /**
     * Banyak pelanggan bisa tampil dengan satu IP publik (NAT router), jadi tampilan biasa diberi
     * batas longgar. Cek tagihan dibatasi per IP dan per kode pelanggan agar 4 digit nomor WA
     * tidak bisa ditebak dengan mencoba semua kombinasi.
     *
     * @return Limit|list<Limit>
     */
    protected function isolationPageLimits(Request $request): Limit|array
    {
        $code = strtoupper($request->string('kode')->trim()->toString());

        if ($code === '') {
            return Limit::perMinute(self::ISOLATION_PAGE_VIEWS_PER_MINUTE)->by('view:'.$request->ip());
        }

        return [
            Limit::perMinute(self::ISOLATION_LOOKUPS_PER_MINUTE)->by('lookup-ip:'.$request->ip()),
            Limit::perHour(self::ISOLATION_LOOKUPS_PER_CODE_PER_HOUR)->by('lookup-code:'.$code),
        ];
    }

    /**
     * Admin boleh melakukan semua aksi. Mengembalikan null (bukan false) agar role lain tetap dicek policy.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(fn (User $user): ?bool => $user->hasRole(Role::Admin) ? true : null);
    }

    /**
     * Kolom polimorfik menyimpan alias pendek, bukan nama kelas, agar data tetap valid saat kelas dipindah.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'activity_log' => ActivityLog::class,
            'customer' => Customer::class,
            'invoice' => Invoice::class,
            'invoice_item' => InvoiceItem::class,
            'message_log' => MessageLog::class,
            'message_template' => MessageTemplate::class,
            'package' => Package::class,
            'payment' => Payment::class,
            'payment_charge' => PaymentCharge::class,
            'payment_notification' => PaymentNotification::class,
            'router' => Router::class,
            'setting' => Setting::class,
            'subscription' => Subscription::class,
            'user' => User::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Props Inertia tanpa pembungkus `data` (kecuali hasil paginate: data/links/meta).
        JsonResource::withoutWrapping();

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
