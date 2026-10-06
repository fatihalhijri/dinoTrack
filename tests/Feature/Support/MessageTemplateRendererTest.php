<?php

declare(strict_types=1);

use App\Enums\MessageTemplateKey;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageTemplate;
use App\Support\MessageTemplateRenderer;
use Illuminate\Http\Request;

function rendererInvoice(): Invoice
{
    $customer = customerOnProfile(Customer::factory()->active()->state(['name' => 'Budi Santoso']));

    return invoiceDueAt($customer, '2026-10-12')->forceFill(['number' => 'INV/2026/10/00007', 'total' => 1_250_000]);
}

it('mengisi nama, nomor invoice, total rupiah, tanggal Indonesia, dan link bayar bertanda tangan', function () {
    MessageTemplate::factory()->create([
        'key' => MessageTemplateKey::InvoiceIssued,
        'body' => '{nama}|{nomor_invoice}|{total}|{jatuh_tempo}|{link_bayar}',
    ]);
    $invoice = rendererInvoice();

    $body = app(MessageTemplateRenderer::class)->render(MessageTemplateKey::InvoiceIssued, $invoice->customer, $invoice);

    [$name, $number, $total, $dueAt, $link] = explode('|', (string) $body);
    expect($name)->toBe('Budi Santoso')
        ->and($number)->toBe('INV/2026/10/00007')
        ->and($total)->toBe('Rp1.250.000')
        ->and($dueAt)->toBe('12 Oktober 2026')
        ->and($link)->toStartWith(url("/tagihan/{$invoice->id}?signature="))
        ->and(Request::create($link)->hasValidSignature())->toBeTrue();
});

it('membiarkan placeholder yang tidak dikenal agar salah ketik admin terlihat', function () {
    MessageTemplate::factory()->create(['key' => MessageTemplateKey::PaymentReceived, 'body' => 'Halo {nama}, {saldo}']);
    $invoice = rendererInvoice();

    $body = app(MessageTemplateRenderer::class)->render(MessageTemplateKey::PaymentReceived, $invoice->customer, $invoice);

    expect($body)->toBe('Halo Budi Santoso, {saldo}');
});

it('tidak menghasilkan pesan jika template dinonaktifkan admin atau belum ada', function (bool $templateExists) {
    if ($templateExists) {
        MessageTemplate::factory()->create(['key' => MessageTemplateKey::ReminderDue, 'is_active' => false]);
    }
    $invoice = rendererInvoice();

    expect(app(MessageTemplateRenderer::class)->render(MessageTemplateKey::ReminderDue, $invoice->customer, $invoice))->toBeNull();
})->with(['template nonaktif' => true, 'template belum di-seed' => false]);
