<?php

declare(strict_types=1);

use App\Actions\Customers\ActivateNewCustomer;
use App\Enums\CustomerStatus;
use App\Jobs\ApplyCustomerProfileJob;
use App\Jobs\SendInvoiceNotificationJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Router;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function pendingCustomerWithSubscription(int $billingDay = 10, int $price = 150_000, string $registeredAt = '2026-10-01'): Customer
{
    $customer = Customer::factory()->pending()->create(['created_at' => $registeredAt]);
    Subscription::factory()->for($customer)->for(Package::factory()->create(['price' => $price]))->create([
        'billing_day' => $billingDay,
        'starts_at' => null,
        'price' => $price,
    ]);

    return $customer;
}

it('mengaktifkan pelanggan dan langsung menerbitkan tagihan pertama prorata', function () {
    Queue::fake([SendInvoiceNotificationJob::class]);
    $this->travelTo('2026-10-25 14:00');
    $kasir = User::factory()->create();
    $customer = pendingCustomerWithSubscription(billingDay: 10, price: 150_000);

    app(ActivateNewCustomer::class)->handle($customer, today(), $kasir);

    expect($customer->fresh())
        ->status->toBe(CustomerStatus::Active)
        ->installed_at->toDateString()->toBe('2026-10-25')
        ->and($customer->activeSubscription->starts_at->toDateString())->toBe('2026-10-25');

    $invoice = Invoice::query()->whereBelongsTo($customer)->sole();
    expect($invoice)
        ->period_start->toDateString()->toBe('2026-10-25')
        ->period_end->toDateString()->toBe('2026-11-09')
        ->total->toBe(77_500)
        ->issued_at->toDateString()->toBe('2026-10-25');

    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.activated', 'subject_id' => $customer->id, 'user_id' => $kasir->id]);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'invoice.issued', 'subject_id' => $invoice->id, 'user_id' => $kasir->id]);
    Queue::assertPushed(SendInvoiceNotificationJob::class);
});

it('menagih periode penuh jika tanggal pasang sama dengan billing_day', function () {
    $this->travelTo('2026-10-10 09:00');
    $customer = pendingCustomerWithSubscription(billingDay: 10, price: 150_000);

    app(ActivateNewCustomer::class)->handle($customer, today());

    expect(Invoice::query()->whereBelongsTo($customer)->sole())
        ->period_end->toDateString()->toBe('2026-11-09')
        ->total->toBe(150_000);
});

it('menagih harga penuh untuk tagihan pertama jika prorata dimatikan', function () {
    Setting::query()->create(['key' => 'billing.prorate_first_month', 'value' => false]);
    $this->travelTo('2026-10-25 09:00');
    $customer = pendingCustomerWithSubscription(billingDay: 10, price: 150_000);

    app(ActivateNewCustomer::class)->handle($customer, today());

    expect(Invoice::query()->whereBelongsTo($customer)->value('total'))->toBe(150_000);
});

it('ikut menagih periode berikutnya jika tanggal pasang mundur melewati billing_day', function () {
    $this->travelTo('2026-10-12 09:00');
    $customer = pendingCustomerWithSubscription(billingDay: 10, registeredAt: '2026-09-20');

    app(ActivateNewCustomer::class)->handle($customer, CarbonImmutable::parse('2026-09-25'));

    expect(Invoice::query()->whereBelongsTo($customer)->orderBy('period_start')->pluck('period_start')->map->toDateString()->all())
        ->toBe(['2026-09-25', '2026-10-10']);
});

it('menolak pelanggan yang bukan pending', function (Closure $makeCustomer) {
    $customer = $makeCustomer();

    expect(fn () => app(ActivateNewCustomer::class)->handle($customer, today()))
        ->toThrow(ValidationException::class, 'Hanya pelanggan yang belum terpasang');

    expect(Invoice::query()->count())->toBe(0);
})->with([
    'active' => [fn () => Customer::factory()->active()->withSubscription()->create()],
    'terminated' => [fn () => Customer::factory()->terminated()->withSubscription()->create()],
]);

it('menolak tanggal pasang di masa depan atau sebelum tanggal pendaftaran', function (string $installedAt, string $message) {
    $this->travelTo('2026-10-15 09:00');
    $customer = pendingCustomerWithSubscription(registeredAt: '2026-10-05');

    expect(fn () => app(ActivateNewCustomer::class)->handle($customer, CarbonImmutable::parse($installedAt)))
        ->toThrow(ValidationException::class, $message);

    expect($customer->fresh()->status)->toBe(CustomerStatus::Pending)
        ->and(Invoice::query()->count())->toBe(0);
})->with([
    'masa depan' => ['2026-10-16', 'masa depan'],
    'sebelum pendaftaran' => ['2026-10-04', 'sebelum tanggal pendaftaran'],
]);

it('menolak aktivasi jika router pelanggan nonaktif', function () {
    $customer = pendingCustomerWithSubscription();
    Router::query()->whereKey($customer->router_id)->update(['is_active' => false]);

    expect(fn () => app(ActivateNewCustomer::class)->handle($customer, today()))
        ->toThrow(ValidationException::class, 'Router pelanggan nonaktif');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Pending);
});

it('membatalkan aktivasi jika penerbitan tagihan pertama gagal', function () {
    $this->travelTo('2026-10-25 09:00');
    $customer = pendingCustomerWithSubscription();
    Invoice::creating(fn () => throw new RuntimeException('Simulasi galat.'));

    expect(fn () => app(ActivateNewCustomer::class)->handle($customer, today()))->toThrow(RuntimeException::class);

    expect($customer->fresh()->status)->toBe(CustomerStatus::Pending)
        ->and($customer->activeSubscription->starts_at)->toBeNull();
});

it('tidak mengirim notifikasi tagihan yang sudah terbit jika aktivasi gagal di tengah jalan', function () {
    // Queue::fake() mengabaikan afterCommit, jadi dipakai queue sync sungguhan dan dicatat
    // job yang benar-benar dijalankan.
    $processed = [];
    Queue::before(function (JobProcessing $event) use (&$processed): void {
        $processed[] = $event->job->resolveName();
    });
    $this->travelTo('2026-10-12 09:00');
    $customer = pendingCustomerWithSubscription(billingDay: 10, registeredAt: '2026-09-20');
    $created = 0;
    // Invoice periode pertama berhasil (dan notifikasinya dijadwalkan), invoice kedua gagal.
    Invoice::creating(function () use (&$created): void {
        if (++$created === 2) {
            throw new RuntimeException('Simulasi galat.');
        }
    });

    expect(fn () => app(ActivateNewCustomer::class)->handle($customer, CarbonImmutable::parse('2026-09-25')))
        ->toThrow(RuntimeException::class);

    expect(Invoice::query()->count())->toBe(0)
        ->and($processed)->not->toContain(SendInvoiceNotificationJob::class)
        ->and($processed)->not->toContain(ApplyCustomerProfileJob::class);
});

it('menjalankan notifikasi tagihan dan pengaktifan secret setelah aktivasi berhasil di-commit', function () {
    $processed = [];
    Queue::before(function (JobProcessing $event) use (&$processed): void {
        $processed[] = $event->job->resolveName();
    });
    $network = fakeNetwork();
    $this->travelTo('2026-10-25 09:00');
    $customer = pendingCustomerWithSubscription();
    $customer->activeSubscription->package->update(['mikrotik_profile' => 'Home-20']);

    app(ActivateNewCustomer::class)->handle($customer, today());

    expect($processed)->toEqualCanonicalizing([SendInvoiceNotificationJob::class, ApplyCustomerProfileJob::class])
        ->and($network->calls('activate')[0]['args'][1])->toBe('Home-20');
});

it('menolak aktivasi pelanggan yang tidak punya langganan aktif', function () {
    $customer = Customer::factory()->pending()->create();

    expect(fn () => app(ActivateNewCustomer::class)->handle($customer, today()))
        ->toThrow(ValidationException::class, 'belum punya langganan aktif');

    expect($customer->fresh()->status)->toBe(CustomerStatus::Pending);
});
