<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Package;
use App\Models\Subscription;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan daftar paket dengan pencarian dan jumlah pemakai', function () {
    $home = Package::factory()->create(['name' => 'Home 20 Mbps', 'mikrotik_profile' => 'H-20']);
    Subscription::factory()->for($home)->count(2)->create();
    Package::factory()->create(['name' => 'Bisnis 50 Mbps', 'mikrotik_profile' => 'B-50']);

    $this->actingAs(userWithRole(Role::Teknisi))
        ->get(route('packages.index', ['search' => 'home']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('packages/index')
            ->has('packages.data', 1, fn (Assert $package) => $package
                ->where('name', 'Home 20 Mbps')
                ->where('subscriptions_count', 2)
                ->etc())
            ->where('packages.meta.total', 1)
            ->where('filters', ['search' => 'home']));
});

it('mengirim daftar berhalaman dengan bentuk data, links, dan meta untuk frontend', function () {
    Package::factory()->count(3)->create();

    $response = $this->actingAs(userWithRole(Role::Teknisi))->get(route('packages.index', ['per_page' => 10]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('packages', fn (Assert $packages) => $packages
            ->has('data', 3)
            ->has('links', fn (Assert $links) => $links->hasAll(['first', 'last', 'prev', 'next']))
            ->has('meta', fn (Assert $meta) => $meta
                ->hasAll(['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'])
                ->has('links.0', fn (Assert $link) => $link->hasAll(['url', 'label', 'page', 'active'])))));
});

it('admin menambah paket dengan harga integer', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('packages.store'), [
            'name' => 'Home 20 Mbps',
            'speed_label' => '20 Mbps',
            'price' => '150000',
            'mikrotik_profile' => 'HOME-20M',
        ])
        ->assertRedirect(route('packages.index'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Paket Home 20 Mbps ditambahkan.']);

    expect(Package::query()->sole())
        ->price->toBe(150_000)
        ->is_active->toBeTrue();
});

it('menampilkan pesan validasi Bahasa Indonesia saat menambah paket', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('packages.store'), [])
        ->assertSessionHasErrors(['name' => 'Nama paket wajib diisi.']);

    expect(Package::query()->count())->toBe(0);
});

it('admin mengubah paket tanpa mengubah status aktif', function () {
    $package = Package::factory()->create(['name' => 'Lama']);

    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('packages.update', $package), [
            'name' => 'Baru',
            'speed_label' => '30 Mbps',
            'price' => 200_000,
            'mikrotik_profile' => 'HOME-30M',
            'is_active' => false,
        ])
        ->assertRedirect(route('packages.index'));

    expect($package->refresh())
        ->name->toBe('Baru')
        ->is_active->toBeTrue();
});

it('menolak menghapus paket yang masih dipakai dengan pesan dari aksi', function () {
    $package = Package::factory()->create();
    Subscription::factory()->for($package)->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->from(route('packages.index'))
        ->delete(route('packages.destroy', $package))
        ->assertRedirect(route('packages.index'))
        ->assertSessionHasErrors(['package' => 'Paket masih dipakai pelanggan dan tidak bisa dihapus. Nonaktifkan paket sebagai gantinya.']);

    $this->assertModelExists($package);
});

it('admin menghapus paket yang belum dipakai', function () {
    $package = Package::factory()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->delete(route('packages.destroy', $package))
        ->assertRedirect(route('packages.index'));

    $this->assertModelMissing($package);
});

it('admin menonaktifkan dan mengaktifkan kembali paket', function () {
    $admin = userWithRole(Role::Admin);
    $package = Package::factory()->create();

    $this->actingAs($admin)->post(route('packages.deactivate', $package))->assertRedirect();
    expect($package->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->post(route('packages.activate', $package))->assertRedirect();
    expect($package->refresh()->is_active)->toBeTrue();
});
