<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Database\UniqueConstraintViolationException;

it('menolak subscription aktif kedua untuk pelanggan yang sama', function () {
    $customer = Customer::factory()->create();
    Subscription::factory()->for($customer)->create();

    Subscription::factory()->for($customer)->create();
})->throws(UniqueConstraintViolationException::class);

it('mengizinkan subscription baru setelah yang lama diakhiri', function () {
    $customer = Customer::factory()->create();
    $old = Subscription::factory()->for($customer)->create();
    $old->update(['ends_at' => today()]);

    Subscription::factory()->for($customer)->create();

    expect($customer->subscriptions()->count())->toBe(2)
        ->and(Subscription::current()->whereBelongsTo($customer)->count())->toBe(1);
});

it('mengunci harga paket saat berlangganan', function () {
    $package = Package::factory()->create(['price' => 150_000]);
    $subscription = Subscription::factory()->for($package)->create();

    $package->update(['price' => 175_000]);

    expect($subscription->fresh()->price)->toBe(150_000);
});

it('menghubungkan paket berikutnya untuk ganti paket', function () {
    $next = Package::factory()->create();

    $subscription = Subscription::factory()->withNextPackage($next)->create();

    expect($subscription->nextPackage->is($next))->toBeTrue()
        ->and($subscription->package->is($next))->toBeFalse();
});
