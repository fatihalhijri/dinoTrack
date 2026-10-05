<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Requests\Customers\ChangeCustomerPackageRequest;
use App\Http\Requests\Customers\ReactivateCustomerRequest;
use App\Http\Requests\Customers\StoreCustomerRequest;
use App\Http\Requests\Customers\TerminateCustomerRequest;
use App\Http\Requests\Customers\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Router;
use Illuminate\Support\Facades\Route;

// Controller baru dibuat di Tahap 09; route ini hanya menjalankan Form Request.
beforeEach(function () {
    Route::middleware('web')->prefix('_test/customers')->group(function () {
        Route::post('/', fn (StoreCustomerRequest $request) => $request->validated());
        Route::put('{customer}', fn (UpdateCustomerRequest $request, Customer $customer) => $request->validated());
        Route::put('{customer}/package', fn (ChangeCustomerPackageRequest $request, Customer $customer) => ['package_id' => $request->packageId()]);
        Route::post('{customer}/terminate', fn (TerminateCustomerRequest $request, Customer $customer) => $request->validated());
        Route::post('{customer}/reactivate', fn (ReactivateCustomerRequest $request, Customer $customer) => [
            'package_id' => $request->packageId(),
            'billing_day' => $request->billingDay(),
        ]);
    });
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customerPayload(array $overrides = []): array
{
    return [
        'name' => 'Budi Santoso',
        'phone' => '0812-3456-7890',
        'address' => 'Jl. Melati No. 1',
        'router_id' => $overrides['router_id'] ?? Router::factory()->create()->id,
        'pppoe_username' => 'budi',
        'package_id' => $overrides['package_id'] ?? Package::factory()->create()->id,
        'billing_day' => 10,
        ...$overrides,
    ];
}

it('mengizinkan pendaftaran pelanggan sesuai permission', function (Role $role, int $status) {
    $this->actingAs(userWithRole($role))
        ->postJson('/_test/customers', customerPayload())
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'teknisi' => [Role::Teknisi, 200],
    'kasir' => [Role::Kasir, 403],
]);

it('menolak tamu', function () {
    $this->postJson('/_test/customers', customerPayload())->assertForbidden();
});

it('menormalisasi nomor WhatsApp sebelum validasi', function () {
    $this->actingAs(userWithRole(Role::Teknisi))
        ->postJson('/_test/customers', customerPayload(['phone' => '+62 812 3456 7890']))
        ->assertOk()
        ->assertJsonPath('phone', '6281234567890');
});

it('menampilkan pesan validasi dalam Bahasa Indonesia', function () {
    $this->actingAs(userWithRole(Role::Teknisi))
        ->postJson('/_test/customers', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Nama wajib diisi.')
        ->assertJsonPath('errors.phone.0', 'Nomor WhatsApp wajib diisi.')
        ->assertJsonPath('errors.router_id.0', 'Router wajib diisi.')
        ->assertJsonPath('errors.package_id.0', 'Paket wajib diisi.')
        ->assertJsonPath('errors.billing_day.0', 'Tanggal tagih wajib diisi.');
});

it('menolak nomor WhatsApp yang tidak berformat 62xxx', function (string $phone) {
    $this->actingAs(userWithRole(Role::Teknisi))
        ->postJson('/_test/customers', customerPayload(['phone' => $phone]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.phone.0', 'Nomor WhatsApp harus berformat 62xxxxxxxxxx, contoh 6281234567890.');
})->with([
    'tanpa awalan 0' => ['81234567890'],
    'terlalu pendek' => ['0812345'],
    'berisi huruf' => ['0812abc45678'],
    'kode negara lain' => ['+15550100123'],
]);

it('menolak tanggal tagih di luar 1 sampai 31', function (int $billingDay) {
    $this->actingAs(userWithRole(Role::Teknisi))
        ->postJson('/_test/customers', customerPayload(['billing_day' => $billingDay]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.billing_day.0', 'Tanggal tagih harus bernilai antara 1 sampai 31.');
})->with([0, 32]);

it('menolak username PPPoE yang sudah dipakai di router yang sama, termasuk pelanggan terhapus', function (bool $trashed) {
    $router = Router::factory()->create();
    $existing = Customer::factory()->for($router)->create(['pppoe_username' => 'budi']);

    if ($trashed) {
        $existing->delete();
    }

    $this->actingAs(userWithRole(Role::Teknisi))
        ->postJson('/_test/customers', customerPayload(['router_id' => $router->id, 'pppoe_username' => 'budi']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.pppoe_username.0', 'Username PPPoE sudah dipakai pelanggan lain di router ini.');
})->with(['pelanggan aktif' => [false], 'pelanggan terhapus' => [true]]);

it('mengizinkan username PPPoE yang sama di router lain', function () {
    Customer::factory()->create(['pppoe_username' => 'budi']);

    $this->actingAs(userWithRole(Role::Teknisi))
        ->postJson('/_test/customers', customerPayload(['pppoe_username' => 'budi']))
        ->assertOk();
});

it('menolak username PPPoE dengan karakter tidak valid', function () {
    $this->actingAs(userWithRole(Role::Teknisi))
        ->postJson('/_test/customers', customerPayload(['pppoe_username' => 'budi santoso']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pppoe_username');
});

it('mewajibkan latitude dan longitude diisi berpasangan', function () {
    $this->actingAs(userWithRole(Role::Teknisi))
        ->postJson('/_test/customers', customerPayload(['latitude' => -6.2]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('longitude');
});

it('hanya admin yang boleh mengubah data pelanggan', function (Role $role, int $status) {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole($role))
        ->putJson("/_test/customers/{$customer->id}", customerPayload(['router_id' => $customer->router_id, 'pppoe_username' => $customer->pppoe_username]))
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 403],
    'teknisi' => [Role::Teknisi, 403],
]);

it('mengabaikan username milik pelanggan itu sendiri saat mengubah data', function () {
    $customer = Customer::factory()->create(['pppoe_username' => 'budi']);

    $this->actingAs(userWithRole(Role::Admin))
        ->putJson("/_test/customers/{$customer->id}", customerPayload(['router_id' => $customer->router_id, 'pppoe_username' => 'budi']))
        ->assertOk();
});

it('hanya admin yang boleh mengganti paket dan membatalkan dengan nilai kosong', function (Role $role, int $status) {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole($role))
        ->putJson("/_test/customers/{$customer->id}/package", ['package_id' => null])
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 403],
    'teknisi' => [Role::Teknisi, 403],
]);

it('memberikan paket sebagai integer atau null untuk aksi ganti paket', function () {
    $admin = userWithRole(Role::Admin);
    $customer = Customer::factory()->create();
    $package = Package::factory()->create();

    $this->actingAs($admin)
        ->putJson("/_test/customers/{$customer->id}/package", ['package_id' => (string) $package->id])
        ->assertOk()
        ->assertJsonPath('package_id', $package->id);

    $this->actingAs($admin)
        ->putJson("/_test/customers/{$customer->id}/package", ['package_id' => null])
        ->assertOk()
        ->assertJsonPath('package_id', null);
});

it('memberikan paket dan tanggal tagih sebagai integer untuk aksi aktifkan kembali', function () {
    $customer = Customer::factory()->terminated()->create();
    $package = Package::factory()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->postJson("/_test/customers/{$customer->id}/reactivate", ['package_id' => (string) $package->id, 'billing_day' => '15'])
        ->assertOk()
        ->assertJsonPath('package_id', $package->id)
        ->assertJsonPath('billing_day', 15);
});

it('mewajibkan field paket dikirim saat ganti paket', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->putJson("/_test/customers/{$customer->id}/package", [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.package_id.0', 'Paket wajib ada.');
});

it('hanya admin yang boleh memberhentikan pelanggan', function (Role $role, int $status) {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole($role))
        ->postJson("/_test/customers/{$customer->id}/terminate", ['reason' => 'Pindah rumah'])
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 403],
    'teknisi' => [Role::Teknisi, 403],
]);

it('membatasi panjang alasan berhenti', function () {
    $customer = Customer::factory()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->postJson("/_test/customers/{$customer->id}/terminate", ['reason' => str_repeat('a', 501)])
        ->assertUnprocessable()
        ->assertJsonPath('errors.reason.0', 'Alasan berhenti tidak boleh lebih dari 500 karakter.');
});

it('hanya admin yang boleh mengaktifkan kembali dan wajib memilih paket serta tanggal tagih', function () {
    $customer = Customer::factory()->terminated()->create();

    $this->actingAs(userWithRole(Role::Kasir))
        ->postJson("/_test/customers/{$customer->id}/reactivate", [])
        ->assertForbidden();

    $this->actingAs(userWithRole(Role::Admin))
        ->postJson("/_test/customers/{$customer->id}/reactivate", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['package_id', 'billing_day']);
});
