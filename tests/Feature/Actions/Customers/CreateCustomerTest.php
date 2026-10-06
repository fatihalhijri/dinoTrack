<?php

declare(strict_types=1);

use App\Actions\Customers\CreateCustomer;
use App\Enums\CustomerStatus;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newCustomerAttributes(array $overrides = []): array
{
    return [
        'name' => 'Budi Santoso',
        'phone' => '0812-3456-7890',
        'address' => 'Jl. Melati No. 1',
        'odp' => 'ODP-01',
        'latitude' => null,
        'longitude' => null,
        'router_id' => $overrides['router_id'] ?? Router::factory()->create()->id,
        'pppoe_username' => 'budi',
        'notes' => null,
        'package_id' => $overrides['package_id'] ?? Package::factory()->create(['price' => 150_000])->id,
        'billing_day' => 10,
        ...$overrides,
    ];
}

it('mendaftarkan pelanggan pending dengan kode berurutan dan nomor WA ternormalisasi', function () {
    $teknisi = User::factory()->create();

    $customer = app(CreateCustomer::class)->handle(newCustomerAttributes(), $teknisi);

    expect($customer->fresh())
        ->code->toBe('PLG-000001')
        ->phone->toBe('6281234567890')
        ->status->toBe(CustomerStatus::Pending)
        ->installed_at->toBeNull();

    $this->assertDatabaseHas(ActivityLog::class, [
        'action' => 'customer.created',
        'subject_type' => 'customer',
        'subject_id' => $customer->id,
        'user_id' => $teknisi->id,
    ]);
});

it('membuat subscription dengan harga paket terkunci dan tanpa tanggal mulai', function () {
    $package = Package::factory()->create(['price' => 150_000]);

    $customer = app(CreateCustomer::class)->handle(newCustomerAttributes(['package_id' => $package->id]));
    $package->update(['price' => 200_000]);

    expect($customer->activeSubscription)
        ->package_id->toBe($package->id)
        ->price->toBe(150_000)
        ->billing_day->toBe(10)
        ->starts_at->toBeNull();
});

it('membulatkan tanggal tagih 29 sampai 31 menjadi 28', function (int $input, int $expected) {
    $customer = app(CreateCustomer::class)->handle(newCustomerAttributes(['billing_day' => $input]));

    expect($customer->activeSubscription->billing_day)->toBe($expected);
})->with([
    'tanggal 28' => [28, 28],
    'tanggal 29' => [29, 28],
    'tanggal 31' => [31, 28],
    'tanggal 1' => [1, 1],
]);

it('menerima angka berupa string seperti dari input form', function () {
    $customer = app(CreateCustomer::class)->handle(newCustomerAttributes([
        'router_id' => (string) Router::factory()->create()->id,
        'package_id' => (string) Package::factory()->create()->id,
        'billing_day' => '31',
    ]));

    expect($customer->activeSubscription->billing_day)->toBe(28);
});

it('mengabaikan field yang tidak boleh diisi saat mendaftar seperti status dan kode', function () {
    $customer = app(CreateCustomer::class)->handle([
        ...newCustomerAttributes(),
        'status' => 'active',
        'code' => 'PLG-999999',
        'installed_at' => '2026-01-01',
    ]);

    expect($customer->fresh())
        ->status->toBe(CustomerStatus::Pending)
        ->code->toBe('PLG-000001')
        ->installed_at->toBeNull();
});

it('melanjutkan kode pelanggan dari sequence yang sudah ada', function () {
    DB::table('sequences')->insert(['key' => 'customer', 'last_value' => 30]);

    $first = app(CreateCustomer::class)->handle(newCustomerAttributes(['pppoe_username' => 'satu']));
    $second = app(CreateCustomer::class)->handle(newCustomerAttributes(['pppoe_username' => 'dua']));

    expect($first->code)->toBe('PLG-000031')
        ->and($second->code)->toBe('PLG-000032');
});

it('menolak paket atau router yang nonaktif', function (string $field, Closure $makeInactive) {
    $attributes = newCustomerAttributes([$field => $makeInactive()]);

    expect(fn () => app(CreateCustomer::class)->handle($attributes))
        ->toThrow(ValidationException::class, 'sudah nonaktif');

    expect(Customer::query()->count())->toBe(0);
})->with([
    'paket' => ['package_id', fn () => Package::factory()->inactive()->create()->id],
    'router' => ['router_id', fn () => Router::factory()->inactive()->create()->id],
]);

it('membatalkan seluruh perubahan dan tidak memakai nomor kode jika penyimpanan gagal', function () {
    $router = Router::factory()->create();
    app(CreateCustomer::class)->handle(newCustomerAttributes(['router_id' => $router->id, 'pppoe_username' => 'budi']));

    expect(fn () => app(CreateCustomer::class)->handle(newCustomerAttributes(['router_id' => $router->id, 'pppoe_username' => 'budi'])))
        ->toThrow(UniqueConstraintViolationException::class);

    $next = app(CreateCustomer::class)->handle(newCustomerAttributes(['router_id' => $router->id, 'pppoe_username' => 'andi']));

    expect($next->code)->toBe('PLG-000002')
        ->and(Customer::query()->count())->toBe(2);
});
