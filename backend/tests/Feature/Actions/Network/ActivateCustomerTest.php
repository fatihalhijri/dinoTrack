<?php

declare(strict_types=1);

use App\Actions\Network\ActivateCustomer;
use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Exceptions\RouterUnreachableException;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;

// Hari ini 2026-10-20, toleransi default 3 hari: tunggakan lewat toleransi = due_at sebelum 2026-10-17.
beforeEach(fn () => $this->travelTo('2026-10-20 10:00'));

function autoIsolatedCustomer(string $profile = 'Home-20'): Customer
{
    return customerOnProfile(Customer::factory()->isolated(IsolationReason::Overdue), $profile);
}

it('membuka isolir otomatis dengan profil paket lalu mengubah status menjadi aktif', function () {
    $network = fakeNetwork();
    $customer = autoIsolatedCustomer('Home-20');
    $customer->update(['network_error_at' => now()->subHour(), 'network_error' => 'Gagal']);

    expect(app(ActivateCustomer::class)->handle($customer))->toBeTrue();

    expect($network->calls('activate')[0]['args'][1])->toBe('Home-20');
    $customer->refresh();
    expect($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->isolated_at)->toBeNull()
        ->and($customer->isolation_reason)->toBeNull()
        ->and($customer->network_error_at)->toBeNull();
    $log = ActivityLog::query()->where('action', 'customer.isolation_lifted')->sole();
    expect($log->properties)->toMatchArray(['manual' => false, 'previous_isolation_reason' => 'overdue', 'profile' => 'Home-20']);
});

it('memasang profil paket baru yang berlaku selama pelanggan diisolir', function () {
    $network = fakeNetwork();
    $customer = autoIsolatedCustomer('Home-20');
    $customer->activeSubscription->update(['package_id' => Package::factory()->create(['mikrotik_profile' => 'Home-50'])->id]);

    app(ActivateCustomer::class)->handle($customer);

    expect($network->calls('activate')[0]['args'][1])->toBe('Home-50');
});

it('tidak mengubah status jika perintah ke router gagal', function () {
    fakeNetwork()->failWith(new RouterUnreachableException('timeout'));
    $customer = autoIsolatedCustomer();

    expect(fn () => app(ActivateCustomer::class)->handle($customer))->toThrow(RouterUnreachableException::class);

    $customer->refresh();
    expect($customer->status)->toBe(CustomerStatus::Isolated)
        ->and($customer->isolation_reason)->toBe(IsolationReason::Overdue);
    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'customer.isolation_lifted']);
});

it('tidak membuka isolir otomatis selama masih ada tunggakan lain lewat toleransi', function () {
    $network = fakeNetwork();
    $customer = autoIsolatedCustomer();
    invoiceDueAt($customer, '2026-10-16');

    expect(app(ActivateCustomer::class)->handle($customer))->toBeFalse();

    $network->assertNotCalled('activate');
    expect($customer->fresh()->status)->toBe(CustomerStatus::Isolated);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.activation_skipped', 'subject_id' => $customer->id]);
});

it('tetap membuka isolir jika tagihan lain belum lewat toleransi', function () {
    $customer = autoIsolatedCustomer();
    invoiceDueAt($customer, '2026-10-17');

    expect(app(ActivateCustomer::class)->handle($customer))->toBeTrue();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
});

it('tidak membuka isolir secara otomatis', function (Closure $arrange) {
    $network = fakeNetwork();
    $customer = $arrange();

    expect(app(ActivateCustomer::class)->handle($customer))->toBeFalse();

    $network->assertNotCalled('activate');
})->with([
    'isolir manual' => fn () => customerOnProfile(Customer::factory()->isolated(IsolationReason::Manual)),
    'aktivasi otomatis dimatikan' => function () {
        Setting::query()->create(['key' => 'billing.auto_activate', 'value' => false]);

        return autoIsolatedCustomer();
    },
    'pelanggan tidak sedang diisolir' => fn () => customerOnProfile(Customer::factory()->active()),
]);

it('admin membuka isolir apa pun meskipun pelanggan masih menunggak', function (IsolationReason $reason) {
    $network = fakeNetwork();
    $admin = User::factory()->create();
    $customer = customerOnProfile(Customer::factory()->isolated($reason));
    invoiceDueAt($customer, '2026-10-16');

    expect(app(ActivateCustomer::class)->handle($customer, isManual: true, by: $admin, note: 'Janji bayar Jumat'))->toBeTrue();

    $network->assertCalled('activate', 1);
    expect($customer->fresh()->status)->toBe(CustomerStatus::Active);
    $log = ActivityLog::query()->where('action', 'customer.isolation_lifted')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties)->toMatchArray(['manual' => true, 'note' => 'Janji bayar Jumat']);
})->with([IsolationReason::Manual, IsolationReason::Overdue]);

it('tidak menandai aktif jika pelanggan diberhentikan saat router sedang dipanggil', function () {
    $customer = autoIsolatedCustomer();
    fakeNetwork()->whenCalled('activate', fn () => Customer::query()->whereKey($customer->id)->update([
        'status' => CustomerStatus::Terminated,
        'terminated_at' => now(),
    ]));

    expect(app(ActivateCustomer::class)->handle($customer))->toBeFalse();

    expect($customer->fresh()->status)->toBe(CustomerStatus::Terminated);
});
