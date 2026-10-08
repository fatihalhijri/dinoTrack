<?php

declare(strict_types=1);

use App\Actions\Network\IsolateCustomer;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Exceptions\RouterUnreachableException;
use App\Exceptions\SecretNotFoundException;
use App\Jobs\SendIsolationNotificationJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Support\CustomerNetworkLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

// Hari ini 2026-10-20, toleransi default 3 hari: tunggakan lewat toleransi = due_at sebelum 2026-10-17.
beforeEach(fn () => $this->travelTo('2026-10-20 01:15'));

function isolateCustomer(Customer $customer, IsolationReason $reason = IsolationReason::Overdue, ?User $by = null, ?string $note = null): bool
{
    return app(IsolateCustomer::class)->handle($customer, $reason, today(), $by, $note);
}

function overdueActiveCustomer(): Customer
{
    $customer = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($customer, '2026-10-16');

    return $customer;
}

it('mengisolir di router lebih dulu lalu mengubah status pelanggan yang menunggak lewat toleransi', function () {
    Queue::fake([SendIsolationNotificationJob::class]);
    $network = fakeNetwork();
    $customer = overdueActiveCustomer();
    $customer->update(['network_error_at' => now()->subDay(), 'network_error' => 'Gagal kemarin']);

    expect(isolateCustomer($customer))->toBeTrue();

    $network->assertCalled('isolate', 1);
    expect($network->calls('isolate')[0]['args'][0]->is($customer))->toBeTrue();
    $customer->refresh();
    expect($customer->status)->toBe(CustomerStatus::Isolated)
        ->and($customer->isolation_reason)->toBe(IsolationReason::Overdue)
        ->and($customer->isolated_at->toDateTimeString())->toBe('2026-10-20 01:15:00')
        ->and($customer->network_error_at)->toBeNull();
    $log = ActivityLog::query()->where('action', 'customer.isolated')->sole();
    expect($log->user_id)->toBeNull()
        ->and($log->properties['isolation_reason'])->toBe('overdue');
    Queue::assertPushed(SendIsolationNotificationJob::class, fn ($job) => $job->customer->is($customer));
});

it('tidak mengubah status jika perintah ke router gagal', function (Throwable $failure) {
    $network = fakeNetwork()->failWith($failure);
    $customer = overdueActiveCustomer();

    expect(fn () => isolateCustomer($customer))->toThrow($failure::class);

    $customer->refresh();
    expect($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->isolated_at)->toBeNull()
        ->and($customer->isolation_reason)->toBeNull();
    $network->assertCalled('isolate', 1);
    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'customer.isolated']);
})->with([
    'router tidak terjangkau' => fn () => new RouterUnreachableException('timeout'),
    'secret tidak ada' => fn () => new SecretNotFoundException('no such item'),
]);

it('melewati isolir otomatis tanpa menyentuh router jika syaratnya tidak lagi terpenuhi', function (Closure $arrange, CustomerStatus $expectedStatus) {
    $network = fakeNetwork();
    $customer = $arrange();

    expect(isolateCustomer($customer))->toBeFalse();

    $network->assertNotCalled('isolate');
    expect($customer->fresh()->status)->toBe($expectedStatus);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.isolation_skipped', 'subject_id' => $customer->id]);
})->with([
    'sudah membayar sebelum job berjalan' => [function () {
        $customer = overdueActiveCustomer();
        $customer->invoices()->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

        return $customer;
    }, CustomerStatus::Active],
    'jatuh tempo + toleransi tepat hari ini' => [function () {
        $customer = customerOnProfile(Customer::factory()->active());
        invoiceDueAt($customer, '2026-10-17');

        return $customer;
    }, CustomerStatus::Active],
    'isolir otomatis dimatikan' => [function () {
        Setting::query()->create(['key' => 'billing.auto_isolate', 'value' => false]);

        return overdueActiveCustomer();
    }, CustomerStatus::Active],
    'pelanggan berhenti' => [function () {
        $customer = overdueActiveCustomer();
        $customer->update(['status' => CustomerStatus::Terminated, 'terminated_at' => now()]);

        return $customer;
    }, CustomerStatus::Terminated],
    'sudah diisolir' => [function () {
        $customer = overdueActiveCustomer();
        $customer->update(['status' => CustomerStatus::Isolated, 'isolated_at' => now(), 'isolation_reason' => IsolationReason::Manual]);

        return $customer;
    }, CustomerStatus::Isolated],
]);

it('mengisolir manual tanpa syarat tunggakan dan mencatat pelaku serta alasannya', function () {
    $network = fakeNetwork();
    $admin = User::factory()->create();
    $customer = customerOnProfile(Customer::factory()->active());

    isolateCustomer($customer, IsolationReason::Manual, $admin, 'Komplain penyalahgunaan jaringan');

    $network->assertCalled('isolate', 1);
    expect($customer->fresh()->isolation_reason)->toBe(IsolationReason::Manual);
    $log = ActivityLog::query()->where('action', 'customer.isolated')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties['note'])->toBe('Komplain penyalahgunaan jaringan');
});

it('mengubah isolir otomatis menjadi manual tanpa memanggil router lagi', function () {
    Queue::fake([SendIsolationNotificationJob::class]);
    $network = fakeNetwork();
    $customer = customerOnProfile(Customer::factory()->isolated(IsolationReason::Overdue)->state(['isolated_at' => '2026-10-18 01:15:00']));

    isolateCustomer($customer, IsolationReason::Manual, User::factory()->create(), 'Ditahan sampai survei ulang');

    $network->assertNotCalled('isolate');
    $customer->refresh();
    expect($customer->isolation_reason)->toBe(IsolationReason::Manual)
        ->and($customer->isolated_at->toDateTimeString())->toBe('2026-10-18 01:15:00');
    expect(ActivityLog::query()->where('action', 'customer.isolated')->sole()->properties['previous_isolation_reason'])->toBe('overdue');
    Queue::assertNotPushed(SendIsolationNotificationJob::class);
});

it('melewati isolir manual untuk pelanggan yang sudah diisolir manual', function () {
    $network = fakeNetwork();
    $customer = customerOnProfile(Customer::factory()->isolated(IsolationReason::Manual));

    expect(isolateCustomer($customer, IsolationReason::Manual, User::factory()->create(), 'Isolir ulang'))->toBeFalse();

    $network->assertNothingCalled();
});

it('langsung mengaktifkan kembali pelanggan yang melunasi tagihan saat router sedang dipanggil', function () {
    $customer = overdueActiveCustomer();
    // Pembayaran ter-commit sementara router memproses isolir: MarkInvoicePaid melihat status
    // masih `active` sehingga tidak menjadwalkan aktivasi.
    $network = fakeNetwork()->whenCalled('isolate', fn () => $customer->invoices()->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]));

    isolateCustomer($customer);

    expect(array_column($network->calls(), 'method'))->toBe(['isolate', 'activate'])
        ->and($customer->fresh()->status)->toBe(CustomerStatus::Active);
});

it('tidak menandai isolir jika pelanggan diberhentikan saat router sedang dipanggil', function () {
    $customer = overdueActiveCustomer();
    fakeNetwork()->whenCalled('isolate', fn () => Customer::query()->whereKey($customer->id)->update([
        'status' => CustomerStatus::Terminated,
        'terminated_at' => now(),
    ]));

    expect(isolateCustomer($customer))->toBeFalse();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Terminated);
    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'customer.isolated']);
});

it('tidak memanggil router selama perintah router lain untuk pelanggan yang sama masih berjalan', function () {
    $network = fakeNetwork();
    $customer = overdueActiveCustomer();
    Cache::lock(CustomerNetworkLock::key($customer), 120)->get();
    $this->app->instance(CustomerNetworkLock::class, new CustomerNetworkLock(waitSeconds: 0));

    expect(fn () => isolateCustomer($customer))->toThrow(LockTimeoutException::class);

    $network->assertNothingCalled();
    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});
