<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Http\Requests\Payments\RecordManualPaymentRequest;
use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

// Controller baru dibuat di Tahap 09; route ini hanya menjalankan Form Request.
beforeEach(function () {
    Route::middleware('web')->prefix('_test')->group(function () {
        Route::post('invoices/{invoice}/payments', fn (RecordManualPaymentRequest $request, Invoice $invoice) => [
            'method' => $request->paymentMethod()->value,
            'amount' => $request->amount(),
            'paid_at' => $request->paidAt()?->toDateString(),
            'notes' => $request->notes(),
        ]);
    });
});

it('hanya admin dan kasir yang boleh mencatat pembayaran manual', function (Role $role, int $status) {
    $invoice = Invoice::factory()->create();

    $this->actingAs(userWithRole($role))
        ->postJson("/_test/invoices/{$invoice->id}/payments", ['method' => 'cash', 'amount' => 150000])
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 200],
    'kasir' => [Role::Kasir, 200],
    'teknisi' => [Role::Teknisi, 403],
]);

it('mengubah input form menjadi nilai bertipe untuk RecordManualPayment', function () {
    $this->travelTo('2026-10-12 14:00');
    $invoice = Invoice::factory()->create();

    $this->actingAs(userWithRole(Role::Kasir))
        ->postJson("/_test/invoices/{$invoice->id}/payments", [
            'method' => 'transfer',
            'amount' => '150000',
            'paid_at' => '2026-10-10',
            'notes' => 'Transfer BCA',
        ])
        ->assertOk()
        ->assertExactJson(['method' => 'transfer', 'amount' => 150000, 'paid_at' => '2026-10-10', 'notes' => 'Transfer BCA']);
});

it('menganggap tanggal bayar hari ini sebagai saat ini', function () {
    $this->travelTo('2026-10-12 14:00');
    $invoice = Invoice::factory()->create();

    $this->actingAs(userWithRole(Role::Kasir))
        ->postJson("/_test/invoices/{$invoice->id}/payments", ['method' => 'cash', 'amount' => 150000, 'paid_at' => '2026-10-12'])
        ->assertOk()
        ->assertJsonPath('paid_at', null);
});

it('memvalidasi input pembayaran manual', function (array $payload, string $field, string $message) {
    $this->travelTo('2026-10-12 14:00');
    $invoice = Invoice::factory()->create();

    $this->actingAs(userWithRole(Role::Kasir))
        ->postJson("/_test/invoices/{$invoice->id}/payments", $payload)
        ->assertUnprocessable()
        ->assertJsonPath("errors.{$field}.0", $message);
})->with([
    'metode kosong' => [['amount' => 150000], 'method', 'Metode pembayaran wajib diisi.'],
    'nominal kosong' => [['method' => 'cash'], 'amount', 'Nominal wajib diisi.'],
    'metode QRIS' => [['method' => 'qris', 'amount' => 150000], 'method', 'Metode pembayaran yang dipilih tidak valid.'],
    'nominal pecahan' => [['method' => 'cash', 'amount' => '150000.5'], 'amount', 'Nominal harus berupa bilangan bulat.'],
    'tanggal di masa depan' => [['method' => 'cash', 'amount' => 150000, 'paid_at' => '2026-10-13'], 'paid_at', 'Tanggal bayar tidak boleh di masa depan.'],
]);
