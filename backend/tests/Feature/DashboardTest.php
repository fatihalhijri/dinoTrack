<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('summary', null)
        ->where('customer_counts', null));
});

test('dashboard berada di /dashboard tanpa prefix team', function () {
    expect(route('dashboard', absolute: false))->toBe('/dashboard');
});

it('menampilkan ringkasan keuangan hanya untuk admin', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('summary.revenue_this_month')
            ->has('summary.customers_with_network_error')
            ->has('customer_counts'));
});

it('menampilkan jumlah pelanggan per status tanpa ringkasan keuangan untuk kasir dan teknisi', function (Role $role) {
    Customer::factory()->active()->count(2)->create();
    Customer::factory()->pending()->create();
    Customer::factory()->active()->create(['network_error_at' => now()]);

    $this->actingAs(userWithRole($role))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary', null)
            ->where('customer_counts', [
                'pending' => 1,
                'active' => 3,
                'isolated' => 0,
                'terminated' => 0,
                'network_error' => 1,
            ]));
})->with([Role::Kasir, Role::Teknisi]);
