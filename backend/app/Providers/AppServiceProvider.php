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
use App\Services\Network\MikrotikNetworkController;
use App\Services\Payment\MidtransPaymentGateway;
use App\Support\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
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
