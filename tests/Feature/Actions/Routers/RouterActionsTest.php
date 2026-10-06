<?php

declare(strict_types=1);

use App\Actions\Routers\CreateRouter;
use App\Actions\Routers\DeleteRouter;
use App\Actions\Routers\TestRouterConnection;
use App\Actions\Routers\UpdateRouter;
use App\Contracts\NetworkController;
use App\Exceptions\RouterUnreachableException;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Router;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeNetworkController;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function routerAttributes(array $overrides = []): array
{
    return [
        'name' => 'Router Utama',
        'host' => '192.168.88.1',
        'port' => 8728,
        'username' => 'billing',
        'password' => 'rahasia-router',
        'use_ssl' => false,
        'isolation_profile' => 'ISOLIR',
        'is_active' => true,
        ...$overrides,
    ];
}

it('membuat router dengan password terenkripsi dan tidak ikut terserialisasi', function () {
    $admin = User::factory()->create();

    $router = app(CreateRouter::class)->handle(routerAttributes(), $admin);

    expect($router->fresh()->password)->toBe('rahasia-router')
        ->and(DB::table('routers')->value('password'))->not->toBe('rahasia-router')
        ->and($router->toArray())->not->toHaveKey('password');

    $log = ActivityLog::query()->where('action', 'router.created')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties)->not->toHaveKey('password');
});

it('mempertahankan password lama jika password dikosongkan saat mengubah router', function (?string $password) {
    $router = Router::factory()->create(['password' => 'rahasia-lama']);

    app(UpdateRouter::class)->handle($router, routerAttributes(['name' => 'Router Baru', 'password' => $password]));

    expect($router->fresh())
        ->name->toBe('Router Baru')
        ->password->toBe('rahasia-lama');
})->with(['null' => [null], 'string kosong' => ['']]);

it('mengganti password tanpa mencatat nilainya di activity log', function () {
    $router = Router::factory()->create(['password' => 'rahasia-lama']);

    app(UpdateRouter::class)->handle($router, ['password' => 'rahasia-baru']);

    expect($router->fresh()->password)->toBe('rahasia-baru');

    $log = ActivityLog::query()->where('action', 'router.updated')->sole();
    expect($log->properties)->toBe(['changes' => ['password' => ['***', '***']]])
        ->and(json_encode($log->properties))->not->toContain('rahasia');
});

it('mengisi last_connected_at saat tes koneksi berhasil', function () {
    $this->freezeSecond();
    $network = new FakeNetworkController;
    $this->app->instance(NetworkController::class, $network);
    $router = Router::factory()->create();

    $result = app(TestRouterConnection::class)->handle($router);

    expect($result)->toBeTrue()
        ->and($router->fresh()->last_connected_at?->equalTo(now()))->toBeTrue();
    $network->assertCalled('testConnection', 1);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'router.connection_tested', 'subject_id' => $router->id]);
});

it('mengembalikan false tanpa mengisi last_connected_at saat router menolak koneksi', function () {
    $network = new FakeNetworkController;
    $network->connectable = false;
    $this->app->instance(NetworkController::class, $network);
    $router = Router::factory()->create();

    expect(app(TestRouterConnection::class)->handle($router))->toBeFalse()
        ->and($router->fresh()->last_connected_at)->toBeNull();
});

it('mengembalikan false dan mencatat pesan galat saat router tidak bisa dijangkau', function () {
    $network = (new FakeNetworkController)->failWith(new RouterUnreachableException('Connection timed out'));
    $this->app->instance(NetworkController::class, $network);
    $router = Router::factory()->create();

    expect(app(TestRouterConnection::class)->handle($router))->toBeFalse()
        ->and($router->fresh()->last_connected_at)->toBeNull();

    $log = ActivityLog::query()->where('action', 'router.connection_tested')->sole();
    expect($log->properties)->toEqual(['success' => false, 'error' => 'Connection timed out']);
});

it('menghapus router yang tidak punya pelanggan', function () {
    $router = Router::factory()->create();

    app(DeleteRouter::class)->handle($router);

    $this->assertModelMissing($router);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'router.deleted', 'subject_id' => $router->id]);
});

it('menolak menghapus router yang masih punya pelanggan, termasuk yang sudah dihapus', function (bool $trashed) {
    $router = Router::factory()->create();
    $customer = Customer::factory()->for($router)->create();

    if ($trashed) {
        $customer->delete();
    }

    expect(fn () => app(DeleteRouter::class)->handle($router))
        ->toThrow(ValidationException::class, 'Router masih dipakai pelanggan');

    $this->assertModelExists($router);
})->with(['pelanggan aktif' => [false], 'pelanggan soft delete' => [true]]);
