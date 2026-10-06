<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Requests\Customers\ActivateCustomerRequest;
use App\Http\Requests\Invoices\CancelInvoiceRequest;
use App\Http\Requests\Invoices\ReissueInvoiceRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use Illuminate\Support\Facades\Route;

// Controller baru dibuat di Tahap 09; route ini hanya menjalankan Form Request.
beforeEach(function () {
    Route::middleware('web')->prefix('_test')->group(function () {
        Route::post('invoices/{invoice}/cancel', fn (CancelInvoiceRequest $request, Invoice $invoice) => $request->validated());
        Route::post('invoices/{invoice}/reissue', fn (ReissueInvoiceRequest $request, Invoice $invoice) => [
            'package_id' => $request->correctedPackageId(),
        ]);
        Route::post('customers/{customer}/activate', fn (ActivateCustomerRequest $request, Customer $customer) => [
            'installed_at' => $request->installedAt()->toDateString(),
        ]);
    });
});

it('hanya admin yang boleh membatalkan tagihan', function (Role $role, int $status) {
    $invoice = Invoice::factory()->create();

    $this->actingAs(userWithRole($role))
        ->postJson("/_test/invoices/{$invoice->id}/cancel", ['reason' => 'Salah input paket'])
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 403],
    'teknisi' => [Role::Teknisi, 403],
]);

it('mewajibkan alasan pembatalan minimal 5 karakter', function (?string $reason, string $message) {
    $invoice = Invoice::factory()->create();

    $this->actingAs(userWithRole(Role::Admin))
        ->postJson("/_test/invoices/{$invoice->id}/cancel", ['reason' => $reason])
        ->assertUnprocessable()
        ->assertJsonPath('errors.reason.0', $message);
})->with([
    'kosong' => [null, 'Alasan pembatalan wajib diisi.'],
    'terlalu pendek' => ['abc', 'Alasan pembatalan minimal berisi 5 karakter.'],
]);

it('hanya admin yang boleh menerbitkan ulang tagihan', function (Role $role, int $status) {
    $invoice = Invoice::factory()->create();

    $this->actingAs(userWithRole($role))
        ->postJson("/_test/invoices/{$invoice->id}/reissue", [])
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 403],
    'teknisi' => [Role::Teknisi, 403],
]);

it('memberikan paket koreksi sebagai integer atau null', function () {
    $admin = userWithRole(Role::Admin);
    $invoice = Invoice::factory()->create();
    $package = Package::factory()->create();

    $this->actingAs($admin)
        ->postJson("/_test/invoices/{$invoice->id}/reissue", ['package_id' => (string) $package->id])
        ->assertOk()
        ->assertJsonPath('package_id', $package->id);

    $this->actingAs($admin)
        ->postJson("/_test/invoices/{$invoice->id}/reissue", [])
        ->assertOk()
        ->assertJsonPath('package_id', null);

    $this->actingAs($admin)
        ->postJson("/_test/invoices/{$invoice->id}/reissue", ['package_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonPath('errors.package_id.0', 'Paket koreksi yang dipilih tidak valid.');
});

it('mengizinkan admin dan kasir menandai pelanggan terpasang, bukan teknisi', function (Role $role, int $status) {
    $customer = Customer::factory()->pending()->create();

    $this->actingAs(userWithRole($role))
        ->postJson("/_test/customers/{$customer->id}/activate", [])
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 200],
    'teknisi' => [Role::Teknisi, 403],
]);

it('memakai hari ini sebagai tanggal pasang jika tidak diisi', function () {
    $this->travelTo('2026-10-15 09:00');
    $customer = Customer::factory()->pending()->create();

    $this->actingAs(userWithRole(Role::Kasir))
        ->postJson("/_test/customers/{$customer->id}/activate", [])
        ->assertOk()
        ->assertJsonPath('installed_at', '2026-10-15');
});

it('menolak tanggal pasang di masa depan atau dengan format salah', function (string $installedAt) {
    $this->travelTo('2026-10-15 09:00');
    $customer = Customer::factory()->pending()->create();

    $this->actingAs(userWithRole(Role::Kasir))
        ->postJson("/_test/customers/{$customer->id}/activate", ['installed_at' => $installedAt])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('installed_at');
})->with(['2026-10-16', '15-10-2026']);
