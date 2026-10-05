<?php

declare(strict_types=1);

use App\Actions\Customers\ChangeCustomerPackage;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

it('langsung mengganti paket dan harga pelanggan pending', function () {
    $old = Package::factory()->create(['price' => 150_000]);
    $new = Package::factory()->create(['price' => 200_000]);
    $customer = Customer::factory()->pending()->withSubscription($old)->create();

    app(ChangeCustomerPackage::class)->handle($customer, $new->id);

    expect($customer->activeSubscription()->sole())
        ->package_id->toBe($new->id)
        ->price->toBe(200_000)
        ->next_package_id->toBeNull();
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.package_changed', 'subject_id' => $customer->id]);
});

it('menjadwalkan ganti paket untuk periode berikutnya pada pelanggan terpasang', function (Closure $makeCustomer) {
    $old = Package::factory()->create(['price' => 150_000]);
    $new = Package::factory()->create();
    /** @var Customer $customer */
    $customer = $makeCustomer($old);

    app(ChangeCustomerPackage::class)->handle($customer, $new->id);

    expect($customer->activeSubscription()->sole())
        ->package_id->toBe($old->id)
        ->price->toBe(150_000)
        ->next_package_id->toBe($new->id);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.package_change_scheduled', 'subject_id' => $customer->id]);
})->with([
    'active' => [fn (Package $package) => Customer::factory()->active()->withSubscription($package)->create()],
    'isolated' => [fn (Package $package) => Customer::factory()->isolated()->withSubscription($package)->create()],
]);

it('membatalkan rencana ganti paket', function () {
    $customer = Customer::factory()->active()->create();
    Subscription::factory()->for($customer)->withNextPackage()->create();

    app(ChangeCustomerPackage::class)->handle($customer, null);

    expect($customer->activeSubscription()->value('next_package_id'))->toBeNull();
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'customer.package_change_cancelled', 'subject_id' => $customer->id]);
});

it('menolak pembatalan jika tidak ada rencana ganti paket', function () {
    $customer = Customer::factory()->active()->withSubscription()->create();

    app(ChangeCustomerPackage::class)->handle($customer, null);
})->throws(ValidationException::class, 'Tidak ada rencana ganti paket');

it('menolak ganti paket untuk pelanggan yang sudah berhenti', function () {
    $customer = Customer::factory()->terminated()->withSubscription()->create();

    app(ChangeCustomerPackage::class)->handle($customer, Package::factory()->create()->id);
})->throws(ValidationException::class, 'sudah berhenti');

it('menolak paket yang sama dengan paket sekarang', function () {
    $package = Package::factory()->create();
    $customer = Customer::factory()->active()->withSubscription($package)->create();

    app(ChangeCustomerPackage::class)->handle($customer, $package->id);
})->throws(ValidationException::class, 'sama dengan paket yang sedang dipakai');

it('menolak paket yang nonaktif', function () {
    $customer = Customer::factory()->active()->withSubscription()->create();

    expect(fn () => app(ChangeCustomerPackage::class)->handle($customer, Package::factory()->inactive()->create()->id))
        ->toThrow(ValidationException::class, 'Paket sudah nonaktif');

    expect($customer->activeSubscription()->value('next_package_id'))->toBeNull();
});
