<?php

declare(strict_types=1);

use App\Actions\Network\ApplyCustomerProfile;
use App\Enums\CustomerStatus;
use App\Exceptions\RouterUnreachableException;
use App\Models\ActivityLog;
use App\Models\Customer;

it('memasang profil paket dan meng-enable secret pelanggan aktif', function () {
    $network = fakeNetwork();
    $customer = customerOnProfile(Customer::factory()->active(), 'Home-30');
    $customer->update(['network_error_at' => now(), 'network_error' => 'Gagal']);

    expect(app(ApplyCustomerProfile::class)->handle($customer))->toBeTrue();

    expect($network->calls('activate')[0]['args'][1])->toBe('Home-30')
        ->and($customer->fresh()->network_error_at)->toBeNull()
        ->and($customer->fresh()->status)->toBe(CustomerStatus::Active);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.profile_applied', 'subject_id' => $customer->id]);
});

it('tidak menyentuh router untuk pelanggan yang tidak aktif saat job berjalan', function (Closure $factory) {
    $network = fakeNetwork();
    $customer = customerOnProfile($factory());

    expect(app(ApplyCustomerProfile::class)->handle($customer))->toBeFalse();

    $network->assertNothingCalled();
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.profile_skipped', 'subject_id' => $customer->id]);
})->with([
    // Profil isolir tidak boleh tertimpa; profil baru dipasang saat isolir dibuka.
    'diisolir' => fn () => Customer::factory()->isolated(),
    'berhenti' => fn () => Customer::factory()->terminated(),
    'belum terpasang' => fn () => Customer::factory()->pending(),
]);

it('membiarkan galat router lolos tanpa menghapus tanda kegagalan sebelumnya', function () {
    fakeNetwork()->failWith(new RouterUnreachableException('timeout'));
    $customer = customerOnProfile(Customer::factory()->active());
    $customer->update(['network_error_at' => now(), 'network_error' => 'Gagal']);

    expect(fn () => app(ApplyCustomerProfile::class)->handle($customer))->toThrow(RouterUnreachableException::class);

    expect($customer->fresh()->network_error_at)->not->toBeNull();
});
