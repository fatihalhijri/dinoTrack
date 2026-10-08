<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\Role;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Models\PaymentCharge;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo('2026-10-20 10:00'));

it('memfilter daftar tagihan menurut status, periode, dan pelanggan', function (Closure $query, string $expectedNumber) {
    $customer = customerOnProfile(Customer::factory()->active()->state(['name' => 'Budi Santoso']));
    invoiceDueAt($customer, '2026-09-12')->update(['number' => 'INV/2026/09/00001']);
    invoiceDueAt($customer, '2026-10-12', InvoiceStatus::Unpaid)->update(['number' => 'INV/2026/10/00001']);
    $other = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($other, '2026-08-12', InvoiceStatus::Paid)->update(['number' => 'INV/2026/08/00009']);

    $this->actingAs(userWithRole(Role::Kasir))
        ->get(route('invoices.index', $query($customer)))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/index')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.number', $expectedNumber)
            ->where('invoices.data.0.customer.id', fn (int $id): bool => $id > 0)
            ->has('statuses', 4));
})->with([
    'status' => [fn () => ['status' => 'paid'], 'INV/2026/08/00009'],
    'periode' => [fn () => ['period' => '2026-10'], 'INV/2026/10/00001'],
    'pelanggan dan nomor' => [fn (Customer $customer) => ['customer_id' => $customer->id, 'search' => '2026/09'], 'INV/2026/09/00001'],
]);

it('menampilkan detail tagihan dengan link bayar tanpa respons mentah gateway', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-12', InvoiceStatus::Unpaid);
    PaymentCharge::factory()->for($invoice)->create(['raw_response' => ['token' => 'rahasia-gateway']]);

    $response = $this->actingAs(userWithRole(Role::Kasir))->get(route('invoices.show', $invoice));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/show')
            ->where('invoice.number', $invoice->number)
            ->has('invoice.payment_charges', 1, fn (Assert $charge) => $charge->missing('raw_response')->etc())
            ->where('payment_link', fn (string $link): bool => str_contains($link, '/tagihan/'.$invoice->id) && str_contains($link, 'signature='))
            ->where('packages', null));
    expect($response->getContent())->not->toContain('rahasia-gateway');
});

it('memberi admin daftar paket koreksi untuk terbit ulang', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-12', InvoiceStatus::Cancelled);

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('invoices.show', $invoice))
        ->assertInertia(fn (Assert $page) => $page->has('packages', 1));
});

it('admin membatalkan tagihan dengan alasan', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('invoices.cancel', $invoice), ['reason' => 'Salah input paket'])
        ->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->refresh())
        ->status->toBe(InvoiceStatus::Cancelled)
        ->cancelled_reason->toBe('Salah input paket');
});

it('mewajibkan alasan pembatalan', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('invoices.cancel', $invoice), [])
        ->assertSessionHasErrors(['reason' => 'Alasan pembatalan wajib diisi.']);
});

it('admin menerbitkan ulang tagihan yang dibatalkan lalu diarahkan ke tagihan baru', function () {
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Cancelled);

    $response = $this->actingAs(userWithRole(Role::Admin))->post(route('invoices.reissue', $invoice));

    $newInvoice = Invoice::query()->whereKeyNot($invoice->id)->sole();
    $response->assertRedirect(route('invoices.show', $newInvoice));
    expect($newInvoice->period_start->toDateString())->toBe($invoice->period_start->toDateString());
});

it('kasir mengirim ulang tagihan ke WhatsApp pelanggan', function () {
    Queue::fake([SendWhatsAppMessage::class]);
    MessageTemplate::factory()->create(['key' => MessageTemplateKey::InvoiceIssued, 'body' => 'Tagihan {nomor_invoice}']);
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-25', InvoiceStatus::Unpaid);

    $this->actingAs(userWithRole(Role::Kasir))
        ->from(route('invoices.show', $invoice))
        ->post(route('invoices.resend', $invoice))
        ->assertRedirect(route('invoices.show', $invoice))
        ->assertInertiaFlash('toast.type', 'success');

    Queue::assertPushed(SendWhatsAppMessage::class, 1);
});

it('membatasi kirim ulang tagihan 6 kali per menit', function () {
    Queue::fake([SendWhatsAppMessage::class]);
    $kasir = userWithRole(Role::Kasir);
    $invoice = Invoice::factory()->paid()->create();

    foreach (range(1, 6) as $attempt) {
        $this->actingAs($kasir)->post(route('invoices.resend', $invoice))->assertSessionHasErrors('invoice');
    }

    $this->actingAs($kasir)->post(route('invoices.resend', $invoice))->assertTooManyRequests();
});
