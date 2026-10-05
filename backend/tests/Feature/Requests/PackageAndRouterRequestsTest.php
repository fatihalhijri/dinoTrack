<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Requests\Packages\StorePackageRequest;
use App\Http\Requests\Packages\UpdatePackageRequest;
use App\Http\Requests\Routers\StoreRouterRequest;
use App\Http\Requests\Routers\UpdateRouterRequest;
use App\Models\Package;
use App\Models\Router;
use Illuminate\Support\Facades\Route;

// Controller baru dibuat di Tahap 09; route ini hanya menjalankan Form Request.
beforeEach(function () {
    Route::middleware('web')->prefix('_test')->group(function () {
        Route::post('packages', fn (StorePackageRequest $request) => $request->validated());
        Route::put('packages/{package}', fn (UpdatePackageRequest $request, Package $package) => $request->validated());
        Route::post('routers', fn (StoreRouterRequest $request) => $request->validated());
        Route::put('routers/{router}', fn (UpdateRouterRequest $request, Router $router) => $request->validated());
    });
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function packagePayload(array $overrides = []): array
{
    return [
        'name' => 'Home 20 Mbps',
        'speed_label' => '20 Mbps',
        'price' => 150_000,
        'mikrotik_profile' => 'HOME-20M',
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function routerPayload(array $overrides = []): array
{
    return [
        'name' => 'Router Utama',
        'host' => '192.168.88.1',
        'port' => 8728,
        'username' => 'billing',
        'password' => 'rahasia',
        'use_ssl' => false,
        'isolation_profile' => 'ISOLIR',
        'is_active' => true,
        ...$overrides,
    ];
}

it('hanya admin yang boleh menambah dan mengubah paket', function (Role $role, int $status) {
    $user = userWithRole($role);
    $package = Package::factory()->create();

    $this->actingAs($user)->postJson('/_test/packages', packagePayload())->assertStatus($status);
    $this->actingAs($user)->putJson("/_test/packages/{$package->id}", packagePayload(['is_active' => true]))->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 403],
    'teknisi' => [Role::Teknisi, 403],
]);

it('memvalidasi data paket dengan pesan Bahasa Indonesia', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->postJson('/_test/packages', packagePayload(['name' => '', 'price' => 0]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Nama paket wajib diisi.')
        ->assertJsonPath('errors.price.0', 'Harga minimal bernilai 1.');
});

it('menolak harga paket berupa pecahan', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->postJson('/_test/packages', packagePayload(['price' => 150000.5]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.price.0', 'Harga harus berupa bilangan bulat.');
});

it('mewajibkan status aktif saat mengubah paket', function () {
    $package = Package::factory()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->putJson("/_test/packages/{$package->id}", packagePayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_active');
});

it('hanya admin yang boleh menambah dan mengubah router', function (Role $role, int $status) {
    $user = userWithRole($role);
    $router = Router::factory()->create();

    $this->actingAs($user)->postJson('/_test/routers', routerPayload())->assertStatus($status);
    $this->actingAs($user)->putJson("/_test/routers/{$router->id}", routerPayload())->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 403],
    'teknisi' => [Role::Teknisi, 403],
]);

it('mewajibkan password saat menambah router tetapi tidak saat mengubah', function () {
    $admin = userWithRole(Role::Admin);
    $router = Router::factory()->create();

    $this->actingAs($admin)
        ->postJson('/_test/routers', routerPayload(['password' => null]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.password.0', 'Password wajib diisi.');

    $this->actingAs($admin)
        ->putJson("/_test/routers/{$router->id}", routerPayload(['password' => null]))
        ->assertOk();
});

it('menolak port dan host router yang tidak valid', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->postJson('/_test/routers', routerPayload(['port' => 70000, 'host' => 'router utama']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.port.0', 'Port API harus bernilai antara 1 sampai 65535.')
        ->assertJsonPath('errors.host.0', 'Format host tidak valid.');
});
