<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00'));

it('kasir menandai pelanggan terpasang dan tagihan pertama langsung terbit', function () {
    $customer = Customer::factory()->pending()->create(['created_at' => '2026-10-01']);
    Subscription::factory()->for($customer)->create(['billing_day' => 20, 'starts_at' => null, 'price' => 150_000]);

    $this->actingAs(userWithRole(Role::Kasir))
        ->post(route('customers.activate', $customer), ['installed_at' => '2026-10-20'])
        ->assertRedirect(route('customers.show', $customer))
        ->assertInertiaFlash('toast.type', 'success');

    expect($customer->refresh()->status)->toBe(CustomerStatus::Active)
        ->and($customer->invoices()->sole()->total)->toBe(150_000);
});

it('menolak tanggal pasang di masa depan dengan pesan yang jelas', function () {
    $customer = Customer::factory()->pending()->withSubscription()->create();

    $this->actingAs(userWithRole(Role::Kasir))
        ->post(route('customers.activate', $customer), ['installed_at' => '2026-10-21'])
        ->assertSessionHasErrors(['installed_at' => 'Tanggal pasang tidak boleh di masa depan.']);

    expect($customer->refresh()->status)->toBe(CustomerStatus::Pending);
});

it('admin memberhentikan pelanggan aktif beserta alasannya', function () {
    $customer = customerOnProfile(Customer::factory()->active());

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('customers.terminate', $customer), ['reason' => 'Pindah rumah'])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh()->status)->toBe(CustomerStatus::Terminated);
});

it('menampilkan penolakan aksi saat memberhentikan pelanggan pending', function () {
    $customer = Customer::factory()->pending()->withSubscription()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('customers.terminate', $customer))
        ->assertSessionHasErrors(['status' => 'Pelanggan yang belum terpasang tidak bisa diberhentikan. Hapus data pelanggan jika batal pasang.']);
});

it('admin mendaftarkan kembali pelanggan yang berhenti', function () {
    $customer = Customer::factory()->terminated()->withSubscription()->create();
    $package = Package::factory()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('customers.reactivate', $customer), ['package_id' => $package->id, 'billing_day' => 5])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh()->status)->toBe(CustomerStatus::Pending)
        ->and($customer->activeSubscription->package_id)->toBe($package->id);
});

it('admin merencanakan dan membatalkan ganti paket pelanggan aktif', function () {
    $admin = userWithRole(Role::Admin);
    $customer = customerOnProfile(Customer::factory()->active());
    $newPackage = Package::factory()->create();

    $this->actingAs($admin)
        ->put(route('customers.package', $customer), ['package_id' => $newPackage->id])
        ->assertInertiaFlash('toast.message', 'Paket pelanggan diperbarui.');
    expect($customer->activeSubscription()->sole()->next_package_id)->toBe($newPackage->id);

    $this->actingAs($admin)
        ->put(route('customers.package', $customer), ['package_id' => null])
        ->assertInertiaFlash('toast.message', 'Rencana ganti paket dibatalkan.');
    expect($customer->activeSubscription()->sole()->next_package_id)->toBeNull();
});
