<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

// Matriks lengkap role × permission diuji di tests/Feature/Policies. Di sini setiap route admin
// dibuktikan memanggil otorisasi: satu role yang tidak berhak ditolak 403, tamu diarahkan ke login.

/** Boleh dibuka semua role (customers.view, packages.view), sehingga tidak ada role yang ditolak. */
const ROUTES_FOR_ALL_ROLES = ['customers.index', 'customers.show', 'packages.index'];

/**
 * @return array<string, array{string, Closure(): string, Role}>
 */
function protectedRoutes(): array
{
    return [
        'packages.store' => ['post', fn () => route('packages.store'), Role::Kasir],
        'packages.update' => ['put', fn () => route('packages.update', Package::factory()->create()), Role::Kasir],
        'packages.destroy' => ['delete', fn () => route('packages.destroy', Package::factory()->create()), Role::Teknisi],
        'packages.activate' => ['post', fn () => route('packages.activate', Package::factory()->inactive()->create()), Role::Kasir],
        'packages.deactivate' => ['post', fn () => route('packages.deactivate', Package::factory()->create()), Role::Kasir],
        'routers.index' => ['get', fn () => route('routers.index'), Role::Kasir],
        'routers.store' => ['post', fn () => route('routers.store'), Role::Teknisi],
        'routers.update' => ['put', fn () => route('routers.update', Router::factory()->create()), Role::Kasir],
        'routers.destroy' => ['delete', fn () => route('routers.destroy', Router::factory()->create()), Role::Kasir],
        'routers.test' => ['post', fn () => route('routers.test', Router::factory()->create()), Role::Teknisi],
        'customers.create' => ['get', fn () => route('customers.create'), Role::Kasir],
        'customers.store' => ['post', fn () => route('customers.store'), Role::Kasir],
        'customers.edit' => ['get', fn () => route('customers.edit', Customer::factory()->create()), Role::Teknisi],
        'customers.update' => ['put', fn () => route('customers.update', Customer::factory()->create()), Role::Kasir],
        'customers.destroy' => ['delete', fn () => route('customers.destroy', Customer::factory()->pending()->create()), Role::Teknisi],
        'customers.activate' => ['post', fn () => route('customers.activate', Customer::factory()->pending()->create()), Role::Teknisi],
        'customers.terminate' => ['post', fn () => route('customers.terminate', Customer::factory()->create()), Role::Kasir],
        'customers.reactivate' => ['post', fn () => route('customers.reactivate', Customer::factory()->terminated()->create()), Role::Kasir],
        'customers.package' => ['put', fn () => route('customers.package', Customer::factory()->create()), Role::Teknisi],
        'customers.isolate' => ['post', fn () => route('customers.isolate', Customer::factory()->create()), Role::Kasir],
        'customers.release' => ['post', fn () => route('customers.release', Customer::factory()->isolated()->create()), Role::Kasir],
        'invoices.index' => ['get', fn () => route('invoices.index'), Role::Teknisi],
        'invoices.show' => ['get', fn () => route('invoices.show', Invoice::factory()->create()), Role::Teknisi],
        'invoices.cancel' => ['post', fn () => route('invoices.cancel', Invoice::factory()->create()), Role::Kasir],
        'invoices.reissue' => ['post', fn () => route('invoices.reissue', Invoice::factory()->cancelled()->create()), Role::Kasir],
        'invoices.resend' => ['post', fn () => route('invoices.resend', Invoice::factory()->create()), Role::Teknisi],
        'invoices.payments.store' => ['post', fn () => route('invoices.payments.store', Invoice::factory()->create()), Role::Teknisi],
        'payments.index' => ['get', fn () => route('payments.index'), Role::Teknisi],
        'payments.review' => ['patch', fn () => route('payments.review', Payment::factory()->qris()->needsReview()->create()), Role::Kasir],
        'users.index' => ['get', fn () => route('users.index'), Role::Kasir],
        'users.store' => ['post', fn () => route('users.store'), Role::Kasir],
        'users.update' => ['put', fn () => route('users.update', User::factory()->create()), Role::Teknisi],
        'users.destroy' => ['delete', fn () => route('users.destroy', User::factory()->create()), Role::Kasir],
        'users.deactivate' => ['post', fn () => route('users.deactivate', User::factory()->create()), Role::Kasir],
        'users.reactivate' => ['post', fn () => route('users.reactivate', User::factory()->deactivated()->create()), Role::Kasir],
        'settings.business.edit' => ['get', fn () => route('settings.business.edit'), Role::Kasir],
        'settings.business.update' => ['put', fn () => route('settings.business.update'), Role::Kasir],
        'settings.billing.edit' => ['get', fn () => route('settings.billing.edit'), Role::Teknisi],
        'settings.billing.update' => ['put', fn () => route('settings.billing.update'), Role::Kasir],
        'settings.message-templates.index' => ['get', fn () => route('settings.message-templates.index'), Role::Kasir],
        'settings.message-templates.update' => ['put', fn () => route('settings.message-templates.update', MessageTemplate::factory()->create()), Role::Kasir],
        'reports.index' => ['get', fn () => route('reports.index'), Role::Kasir],
        'reports.outstanding' => ['get', fn () => route('reports.outstanding'), Role::Kasir],
        'reports.export.payments' => ['get', fn () => route('reports.export.payments', ['from' => '2026-10-01', 'to' => '2026-10-31']), Role::Kasir],
        'reports.export.outstanding' => ['get', fn () => route('reports.export.outstanding'), Role::Kasir],
        'reports.export.revenue' => ['get', fn () => route('reports.export.revenue'), Role::Teknisi],
    ];
}

it('menolak role yang tidak berhak dengan 403', function (string $method, Closure $url, Role $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->{$method}($url())->assertForbidden();
})->with(protectedRoutes());

it('mengarahkan tamu ke halaman login', function (string $method, Closure $url) {
    $this->{$method}($url())->assertRedirect(route('login'));
})->with(protectedRoutes());

it('mengarahkan tamu dari halaman yang boleh dibuka semua role ke login', function (string $routeName) {
    $parameters = $routeName === 'customers.show' ? [Customer::factory()->create()] : [];

    $this->get(route($routeName, $parameters))->assertRedirect(route('login'));
})->with(ROUTES_FOR_ALL_ROLES);

it('mengarahkan user yang belum verifikasi email ke halaman verifikasi', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->unverified()->create()->assignRole(Role::Admin);

    $this->actingAs($user)->get(route('customers.index'))->assertRedirect(route('verification.notice'));
});

it('mendaftarkan semua route admin di dataset otorisasi', function () {
    $adminRoutes = collect(Route::getRoutes()->getRoutesByName())
        ->filter(fn (RoutingRoute $route): bool => in_array(PermissionMiddleware::class, array_map(
            fn (string $middleware): string => explode(':', $middleware)[0],
            $route->gatherMiddleware(),
        ), true))
        ->keys()
        ->reject(fn (string $name): bool => in_array($name, ROUTES_FOR_ALL_ROLES, true))
        ->sort()
        ->values()
        ->all();

    expect($adminRoutes)->toBe(collect(array_keys(protectedRoutes()))->sort()->values()->all());
});
