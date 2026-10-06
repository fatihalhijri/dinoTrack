<?php

declare(strict_types=1);

use App\Actions\Customers\UpdateCustomer;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Router;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Data form ubah pelanggan berisi nilai yang sedang tersimpan, seperti yang dikirim frontend.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function currentCustomerAttributes(Customer $customer, array $overrides = []): array
{
    return [
        'name' => $customer->name,
        'phone' => $customer->phone,
        'address' => $customer->address,
        'odp' => $customer->odp,
        'latitude' => $customer->latitude,
        'longitude' => $customer->longitude,
        'router_id' => $customer->router_id,
        'pppoe_username' => $customer->pppoe_username,
        'notes' => $customer->notes,
        ...$overrides,
    ];
}

it('mengubah data identitas pelanggan aktif dan mencatat perubahannya', function () {
    $admin = User::factory()->create();
    $customer = Customer::factory()->active()->withSubscription()->create(['name' => 'Budi', 'phone' => '6281111111111']);

    app(UpdateCustomer::class)->handle($customer, currentCustomerAttributes($customer, [
        'name' => 'Budi Santoso',
        'phone' => '0812 2222 2222',
    ]), $admin);

    expect($customer->fresh())
        ->name->toBe('Budi Santoso')
        ->phone->toBe('6281222222222');

    $log = ActivityLog::query()->where('action', 'customer.updated')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties['changes'])->toEqual([
            'name' => ['Budi', 'Budi Santoso'],
            'phone' => ['6281111111111', '6281222222222'],
        ]);
});

it('mengizinkan perubahan router, username, dan tanggal tagih selama pending', function () {
    $customer = Customer::factory()->pending()->withSubscription()->create();
    $router = Router::factory()->create();

    app(UpdateCustomer::class)->handle($customer, currentCustomerAttributes($customer, [
        'router_id' => $router->id,
        'pppoe_username' => 'username-baru',
        'billing_day' => 31,
    ]));

    expect($customer->fresh())
        ->router_id->toBe($router->id)
        ->pppoe_username->toBe('username-baru')
        ->and($customer->activeSubscription()->value('billing_day'))->toBe(28);
});

it('menolak perubahan router, username, atau tanggal tagih setelah pelanggan terpasang', function (string $field, Closure $value) {
    $customer = Customer::factory()->active()->withSubscription()->create();
    $originalBillingDay = $customer->activeSubscription->billing_day;

    expect(fn () => app(UpdateCustomer::class)->handle($customer, currentCustomerAttributes($customer, [
        'name' => 'Nama Baru',
        $field => $value($customer),
    ])))->toThrow(ValidationException::class, 'hanya bisa diubah selama pelanggan belum terpasang');

    expect($customer->fresh()->name)->not->toBe('Nama Baru')
        ->and($customer->activeSubscription()->value('billing_day'))->toBe($originalBillingDay);
})->with([
    'router' => ['router_id', fn () => Router::factory()->create()->id],
    'username PPPoE' => ['pppoe_username', fn () => 'username-baru'],
    'tanggal tagih' => ['billing_day', fn (Customer $customer) => $customer->activeSubscription->billing_day % 28 + 1],
]);

it('menerima nilai router, username, dan tanggal tagih yang tidak berubah untuk pelanggan terpasang', function () {
    $customer = Customer::factory()->active()->withSubscription()->create();

    app(UpdateCustomer::class)->handle($customer, currentCustomerAttributes($customer, [
        'name' => 'Nama Baru',
        'billing_day' => $customer->activeSubscription->billing_day,
    ]));

    expect($customer->fresh()->name)->toBe('Nama Baru');
});

it('menolak pindah ke router yang nonaktif', function () {
    $customer = Customer::factory()->pending()->withSubscription()->create();
    $router = Router::factory()->inactive()->create();

    app(UpdateCustomer::class)->handle($customer, currentCustomerAttributes($customer, ['router_id' => $router->id]));
})->throws(ValidationException::class, 'Router sudah nonaktif');

it('menerima angka berupa string seperti dari input form', function () {
    $active = Customer::factory()->active()->withSubscription()->create();
    $pending = Customer::factory()->pending()->withSubscription()->create();

    app(UpdateCustomer::class)->handle($active, currentCustomerAttributes($active, [
        'name' => 'Nama Baru',
        'router_id' => (string) $active->router_id,
        'billing_day' => (string) $active->activeSubscription->billing_day,
    ]));
    app(UpdateCustomer::class)->handle($pending, currentCustomerAttributes($pending, [
        'router_id' => (string) $pending->router_id,
        'billing_day' => '31',
    ]));

    expect($active->fresh()->name)->toBe('Nama Baru')
        ->and($pending->activeSubscription()->value('billing_day'))->toBe(28);
});

it('mengabaikan field di luar data pelanggan seperti status', function () {
    $customer = Customer::factory()->pending()->withSubscription()->create();

    app(UpdateCustomer::class)->handle($customer, [
        ...currentCustomerAttributes($customer),
        'status' => 'active',
        'installed_at' => '2026-01-01',
    ]);

    expect($customer->fresh())
        ->status->value->toBe('pending')
        ->installed_at->toBeNull();
});

it('tidak mencatat aktivitas jika tidak ada yang berubah', function () {
    $customer = Customer::factory()->active()->withSubscription()->create();

    app(UpdateCustomer::class)->handle($customer, currentCustomerAttributes($customer));

    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'customer.updated']);
});
