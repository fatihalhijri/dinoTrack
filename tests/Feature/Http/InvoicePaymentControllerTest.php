<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Payment;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00'));

it('kasir mencatat pembayaran tunai dan tagihan menjadi lunas', function () {
    $kasir = userWithRole(Role::Kasir);
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs($kasir)
        ->post(route('invoices.payments.store', $invoice), ['method' => 'cash', 'amount' => (string) $invoice->total, 'notes' => 'Dibayar di loket'])
        ->assertRedirect(route('invoices.show', $invoice))
        ->assertInertiaFlash('toast.message', "Pembayaran tagihan {$invoice->number} dicatat.");

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
    expect(Payment::query()->sole())
        ->method->toBe(PaymentMethod::Cash)
        ->received_by->toBe($kasir->id)
        ->notes->toBe('Dibayar di loket');
});

it('mencatat tanggal bayar mundur pada awal hari itu', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs(userWithRole(Role::Kasir))
        ->post(route('invoices.payments.store', $invoice), ['method' => 'transfer', 'amount' => (string) $invoice->total, 'paid_at' => '2026-10-19'])
        ->assertRedirect(route('invoices.show', $invoice));

    expect(Payment::query()->sole())
        ->method->toBe(PaymentMethod::Transfer)
        ->paid_at->toDateTimeString()->toBe('2026-10-19 00:00:00');
});

it('menolak tanggal bayar sebelum tagihan terbit', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs(userWithRole(Role::Kasir))
        ->post(route('invoices.payments.store', $invoice), ['method' => 'cash', 'amount' => (string) $invoice->total, 'paid_at' => '2026-10-17'])
        ->assertSessionHasErrors(['paid_at' => 'Tanggal bayar tidak boleh sebelum tanggal terbit tagihan.']);

    expect(Payment::query()->count())->toBe(0);
    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('menampilkan penolakan nominal yang tidak sama dengan total tagihan', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs(userWithRole(Role::Kasir))
        ->post(route('invoices.payments.store', $invoice), ['method' => 'cash', 'amount' => $invoice->total - 1])
        ->assertSessionHasErrors('amount');

    expect(Payment::query()->count())->toBe(0);
});

it('menolak metode QRIS untuk pembayaran manual', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs(userWithRole(Role::Kasir))
        ->post(route('invoices.payments.store', $invoice), ['method' => 'qris', 'amount' => $invoice->total])
        ->assertSessionHasErrors('method');
});
