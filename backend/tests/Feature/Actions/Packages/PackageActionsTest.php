<?php

declare(strict_types=1);

use App\Actions\Packages\CreatePackage;
use App\Actions\Packages\DeactivatePackage;
use App\Actions\Packages\DeletePackage;
use App\Actions\Packages\UpdatePackage;
use App\Models\ActivityLog;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('membuat paket aktif dan mencatat aktivitas', function () {
    $admin = User::factory()->create();

    $package = app(CreatePackage::class)->handle([
        'name' => 'Home 20 Mbps',
        'speed_label' => '20 Mbps',
        'price' => 150_000,
        'mikrotik_profile' => 'HOME-20M',
    ], $admin);

    expect($package->fresh())
        ->name->toBe('Home 20 Mbps')
        ->price->toBe(150_000)
        ->is_active->toBeTrue();

    $this->assertDatabaseHas(ActivityLog::class, [
        'action' => 'package.created',
        'subject_type' => 'package',
        'subject_id' => $package->id,
        'user_id' => $admin->id,
    ]);
});

it('mengubah paket tanpa mengubah harga subscription yang sudah ada', function () {
    $package = Package::factory()->create(['price' => 150_000]);
    $subscription = Subscription::factory()->for($package)->create();

    app(UpdatePackage::class)->handle($package, ['price' => 175_000]);

    expect($package->fresh()->price)->toBe(175_000)
        ->and($subscription->fresh()->price)->toBe(150_000);

    $log = ActivityLog::query()->where('action', 'package.updated')->sole();
    expect($log->properties)->toBe(['changes' => ['price' => [150_000, 175_000]]]);
});

it('tidak mencatat aktivitas jika tidak ada yang berubah', function () {
    $package = Package::factory()->create(['name' => 'Home 20 Mbps']);

    app(UpdatePackage::class)->handle($package, ['name' => 'Home 20 Mbps']);

    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'package.updated']);
});

it('menonaktifkan paket tanpa menyentuh subscription yang memakainya', function () {
    $package = Package::factory()->create();
    $subscription = Subscription::factory()->for($package)->create();

    app(DeactivatePackage::class)->handle($package);

    expect($package->fresh()->is_active)->toBeFalse()
        ->and($subscription->fresh()->package_id)->toBe($package->id);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'package.deactivated', 'subject_id' => $package->id]);
});

it('menolak menonaktifkan paket yang sudah nonaktif', function () {
    app(DeactivatePackage::class)->handle(Package::factory()->inactive()->create());
})->throws(ValidationException::class, 'Paket sudah nonaktif.');

it('menghapus paket yang belum pernah dipakai', function () {
    $package = Package::factory()->create();

    app(DeletePackage::class)->handle($package);

    $this->assertModelMissing($package);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'package.deleted', 'subject_id' => $package->id]);
});

it('menolak menghapus paket yang masih dirujuk subscription', function (Closure $makeSubscription) {
    $package = Package::factory()->create();
    $makeSubscription($package);

    expect(fn () => app(DeletePackage::class)->handle($package))
        ->toThrow(ValidationException::class, 'Paket masih dipakai pelanggan');

    $this->assertModelExists($package);
})->with([
    'subscription aktif' => [fn (Package $package) => Subscription::factory()->for($package)->create()],
    'riwayat subscription' => [fn (Package $package) => Subscription::factory()->for($package)->ended()->create()],
    'rencana ganti paket' => [fn (Package $package) => Subscription::factory()->withNextPackage($package)->create()],
]);
