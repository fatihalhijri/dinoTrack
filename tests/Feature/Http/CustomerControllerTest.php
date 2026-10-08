<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\Role;
use App\Exceptions\RouterUnreachableException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Router;
use Inertia\Testing\AssertableInertia as Assert;

it('memfilter daftar pelanggan menurut status, paket, router, dan galat router', function (array $query, string $expectedName) {
    $package = Package::factory()->create();
    $router = Router::factory()->create();
    Customer::factory()->withSubscription($package)->create(['name' => 'Paket Cocok']);
    Customer::factory()->for($router)->create(['name' => 'Router Cocok']);
    Customer::factory()->isolated()->create(['name' => 'Terisolir']);
    Customer::factory()->create(['name' => 'Galat Router', 'network_error_at' => now()]);
    Customer::factory()->create(['name' => 'Budi Santoso', 'code' => 'PLG-000777']);

    $query = array_map(fn (mixed $value): mixed => $value instanceof Closure ? $value($package, $router) : $value, $query);

    $this->actingAs(userWithRole(Role::Teknisi))
        ->get(route('customers.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index', true)
            ->has('customers.data', 1)
            ->where('customers.data.0.name', $expectedName)
            ->has('statuses', 4)
            ->has('packages')
            ->has('routers'));
})->with([
    'status' => [['status' => 'isolated'], 'Terisolir'],
    'paket' => [['package_id' => fn (Package $package) => $package->id], 'Paket Cocok'],
    'router' => [['router_id' => fn (Package $package, Router $router) => $router->id], 'Router Cocok'],
    'galat router' => [['network_error' => '1'], 'Galat Router'],
    'kode pelanggan' => [['search' => 'PLG-000777'], 'Budi Santoso'],
]);

it('mengembalikan filter daftar pelanggan yang dikirim untuk mengisi ulang form filter', function () {
    $package = Package::factory()->create();
    $router = Router::factory()->create();
    $query = [
        'search' => 'budi',
        'status' => 'active',
        'package_id' => (string) $package->id,
        'router_id' => (string) $router->id,
        'network_error' => '1',
        'per_page' => '50',
    ];

    $this->actingAs(userWithRole(Role::Teknisi))
        ->get(route('customers.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index', true)
            ->where('filters', $query));
});

it('menampilkan paket, router, dan galat router setiap baris daftar pelanggan', function () {
    $customer = customerOnProfile(Customer::factory()->active()->state(['network_error_at' => now(), 'network_error' => 'Router tidak dapat dihubungi.']));

    $this->actingAs(userWithRole(Role::Kasir))
        ->get(route('customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index', true)
            ->where('filters', [])
            ->has('customers.data', 1, fn (Assert $row) => $row
                ->where('router.name', $customer->router->name)
                ->where('subscription.package.name', $customer->activeSubscription->package->name)
                ->where('subscription.package.speed_label', $customer->activeSubscription->package->speed_label)
                ->where('network_error', 'Router tidak dapat dihubungi.')
                ->where('network_error_at', $customer->network_error_at->toIso8601String())
                ->etc()));
});

it('mencari tanda persen sebagai teks biasa, bukan wildcard', function () {
    Customer::factory()->create(['name' => 'Diskon 100% Net']);
    Customer::factory()->create(['name' => 'Budi Santoso']);

    $this->actingAs(userWithRole(Role::Teknisi))
        ->get(route('customers.index', ['search' => '%']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Diskon 100% Net'));
});

it('menolak filter status yang tidak dikenal', function () {
    $this->actingAs(userWithRole(Role::Teknisi))
        ->get(route('customers.index', ['status' => 'hapus']))
        ->assertSessionHasErrors('status');
});

it('membuka form tambah pelanggan untuk teknisi dengan paket dan router aktif saja', function () {
    $package = Package::factory()->create(['name' => 'Home 20 Mbps', 'speed_label' => '20 Mbps', 'price' => 150000]);
    Package::factory()->inactive()->create();
    $router = Router::factory()->create(['name' => 'Router Utama']);
    Router::factory()->inactive()->create();

    $this->actingAs(userWithRole(Role::Teknisi))
        ->get(route('customers.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/create', true)
            ->where('packages', [['id' => $package->id, 'name' => 'Home 20 Mbps', 'speed_label' => '20 Mbps', 'price' => 150000]])
            ->where('routers', [['id' => $router->id, 'name' => 'Router Utama']]));
});

it('teknisi mendaftarkan pelanggan pending lalu diarahkan ke detailnya', function () {
    $package = Package::factory()->create();
    $router = Router::factory()->create();

    $response = $this->actingAs(userWithRole(Role::Teknisi))->post(route('customers.store'), [
        'name' => 'Budi Santoso',
        'phone' => '0812-3456-7890',
        'address' => 'Jl. Melati 1',
        'router_id' => (string) $router->id,
        'pppoe_username' => 'budi',
        'package_id' => (string) $package->id,
        'billing_day' => '10',
    ]);

    $customer = Customer::query()->sole();
    $response->assertRedirect(route('customers.show', $customer))
        ->assertInertiaFlash('toast.message', "Pelanggan Budi Santoso ({$customer->code}) didaftarkan.");
    expect($customer)
        ->status->toBe(CustomerStatus::Pending)
        ->phone->toBe('6281234567890')
        ->and($customer->activeSubscription->billing_day)->toBe(10);
});

it('menampilkan detail pelanggan tanpa riwayat tagihan untuk teknisi', function () {
    $customer = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($customer, '2026-10-12');

    $this->actingAs(userWithRole(Role::Teknisi))
        ->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/show')
            ->where('customer.id', $customer->id)
            ->where('customer.subscription.package.name', $customer->activeSubscription->package->name)
            ->where('invoices', null)
            ->where('payments', null)
            ->where('messages', null)
            ->where('packages', null)
            ->has('activities')
            ->missing('connection'));
});

it('menampilkan riwayat tagihan pelanggan untuk kasir', function () {
    $customer = customerOnProfile(Customer::factory()->active());
    $invoice = invoiceDueAt($customer, '2026-10-12');

    $this->actingAs(userWithRole(Role::Kasir))
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.number', $invoice->number)
            ->has('payments', 0)
            ->has('messages', 0));
});

it('memuat status koneksi pelanggan dari router setelah halaman tampil', function (Closure $configure, array $expected) {
    $configure(fakeNetwork());
    $customer = Customer::factory()->active()->create();

    $this->actingAs(userWithRole(Role::Teknisi))
        ->get(route('customers.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('connection')
            ->loadDeferredProps(fn (Assert $reload) => $reload->where('connection', $expected)));
})->with([
    'online' => [fn ($network) => $network->online = true, ['online' => true, 'error' => null]],
    'router tidak terjangkau' => [fn ($network) => $network->failWith(new RouterUnreachableException('timeout')), ['online' => null, 'error' => 'Router tidak dapat dihubungi.']],
]);

it('menyertakan router nonaktif yang sedang dipakai di form ubah pelanggan, bukan router nonaktif lain', function () {
    $currentRouter = Router::factory()->inactive()->create(['name' => 'A Router Lama']);
    Router::factory()->inactive()->create(['name' => 'B Router Mati']);
    $activeRouter = Router::factory()->create(['name' => 'C Router Aktif']);
    $customer = Customer::factory()->for($currentRouter)->withSubscription()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('customers.edit', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/edit', true)
            ->where('customer.subscription.billing_day', $customer->activeSubscription->billing_day)
            ->where('routers', [
                ['id' => $currentRouter->id, 'name' => 'A Router Lama'],
                ['id' => $activeRouter->id, 'name' => 'C Router Aktif'],
            ]));
});

it('menampilkan penolakan aksi saat router pelanggan terpasang diubah', function () {
    $customer = customerOnProfile(Customer::factory()->active());
    $newRouter = Router::factory()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('customers.update', $customer), [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'router_id' => $newRouter->id,
            'pppoe_username' => $customer->pppoe_username,
        ])
        ->assertSessionHasErrors('router_id');

    expect($customer->refresh()->router_id)->not->toBe($newRouter->id);
});

it('admin mengubah data identitas pelanggan', function () {
    $customer = customerOnProfile(Customer::factory()->active());

    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('customers.update', $customer), [
            'name' => 'Nama Baru',
            'phone' => $customer->phone,
            'address' => 'Alamat Baru',
            'router_id' => $customer->router_id,
            'pppoe_username' => $customer->pppoe_username,
        ])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh())
        ->name->toBe('Nama Baru')
        ->address->toBe('Alamat Baru');
});

it('admin mengubah identitas pelanggan berhenti tanpa mengirim tanggal tagih', function () {
    $customer = Customer::factory()->terminated()->withSubscription()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('customers.update', $customer), [
            'name' => 'Nama Baru',
            'phone' => '0812-3456-7890',
            'address' => $customer->address,
            'router_id' => (string) $customer->router_id,
            'pppoe_username' => $customer->pppoe_username,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh())
        ->name->toBe('Nama Baru')
        ->phone->toBe('6281234567890');
});

it('admin menghapus pelanggan pending yang salah input', function () {
    $customer = Customer::factory()->pending()->withSubscription()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    $this->assertSoftDeleted($customer);
});

it('menolak menghapus pelanggan yang sudah punya tagihan', function () {
    $customer = Customer::factory()->pending()->withSubscription()->create();
    Invoice::factory()->for($customer->subscriptions()->sole())->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->delete(route('customers.destroy', $customer))
        ->assertSessionHasErrors('customer');

    $this->assertNotSoftDeleted($customer);
});

it('tidak mengirim data sensitif router di halaman detail pelanggan', function () {
    $customer = customerOnProfile(Customer::factory()->active());
    $customer->router->update(['password' => 'rahasia-router']);

    $response = $this->actingAs(userWithRole(Role::Admin))->get(route('customers.show', $customer));

    $response->assertInertia(fn (Assert $page) => $page->where('customer.router', ['id' => $customer->router_id, 'name' => $customer->router->name]));
    expect($response->getContent())->not->toContain('rahasia-router');
});
