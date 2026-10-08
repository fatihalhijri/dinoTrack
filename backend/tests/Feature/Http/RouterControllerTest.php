<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Exceptions\RouterUnreachableException;
use App\Models\Customer;
use App\Models\Router;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function routerForm(array $overrides = []): array
{
    return [
        'name' => 'Router Utama',
        'host' => '192.168.88.1',
        'port' => '8728',
        'username' => 'billing',
        'password' => 'rahasia-router',
        'use_ssl' => '0',
        'isolation_profile' => 'ISOLIR',
        'is_active' => '1',
        ...$overrides,
    ];
}

it('menampilkan daftar router tanpa password', function () {
    Router::factory()->create(['name' => 'Router Utama', 'password' => 'rahasia-router']);

    $response = $this->actingAs(userWithRole(Role::Admin))->get(route('routers.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('routers/index')
            ->has('routers.data', 1, fn (Assert $router) => $router
                ->where('name', 'Router Utama')
                ->missing('password')
                ->etc()));
    expect($response->getContent())->not->toContain('rahasia-router');
});

it('admin menambah router dengan password terenkripsi', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('routers.store'), routerForm())
        ->assertRedirect(route('routers.index'));

    expect(Router::query()->sole())
        ->port->toBe(8728)
        ->use_ssl->toBeFalse()
        ->password->toBe('rahasia-router');
});

it('mempertahankan password router jika dikosongkan saat mengubah', function () {
    $router = Router::factory()->create(['password' => 'lama']);

    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('routers.update', $router), routerForm(['name' => 'Router Baru', 'password' => '']))
        ->assertRedirect(route('routers.index'));

    expect($router->refresh())
        ->name->toBe('Router Baru')
        ->password->toBe('lama');
});

it('menolak menghapus router yang masih punya pelanggan', function () {
    $router = Router::factory()->create();
    Customer::factory()->for($router)->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->delete(route('routers.destroy', $router))
        ->assertSessionHasErrors(['router' => 'Router masih dipakai pelanggan dan tidak bisa dihapus. Nonaktifkan router sebagai gantinya.']);

    $this->assertModelExists($router);
});

it('menampilkan hasil tes koneksi router', function (bool $isReachable, array $toast) {
    $network = fakeNetwork();

    if (! $isReachable) {
        $network->failWith(new RouterUnreachableException('Router Utama (192.168.88.1) tidak dapat dihubungi.'));
    }

    $router = Router::factory()->create(['name' => 'Router Utama']);

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('routers.test', $router))
        ->assertRedirect()
        ->assertInertiaFlash('toast', $toast);
})->with([
    'terhubung' => [true, ['type' => 'success', 'message' => 'Router Router Utama terhubung.']],
    'gagal' => [false, ['type' => 'error', 'message' => 'Router Router Utama tidak dapat dihubungi. Periksa host, port, dan akun API.']],
]);
