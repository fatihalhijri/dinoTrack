<?php

declare(strict_types=1);

use App\Actions\Invoices\ResendInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Jobs\SendWhatsAppMessage;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Queue::fake([SendWhatsAppMessage::class]));

function resendableInvoice(InvoiceStatus $status = InvoiceStatus::Unpaid): Invoice
{
    MessageTemplate::factory()->create(['key' => MessageTemplateKey::InvoiceIssued, 'body' => 'Tagihan {nomor_invoice}']);

    return invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-12', $status);
}

it('mengirim ulang tagihan meskipun pesan sebelumnya sudah terkirim', function () {
    $invoice = resendableInvoice();
    MessageLog::factory()->for($invoice)->create(['template_key' => MessageTemplateKey::InvoiceIssued, 'status' => MessageStatus::Sent]);
    $kasir = User::factory()->create();

    $messageLog = app(ResendInvoice::class)->handle($invoice, $kasir);

    expect($messageLog->status)->toBe(MessageStatus::Queued)
        ->and($messageLog->body)->toBe("Tagihan {$invoice->number}");
    Queue::assertPushed(SendWhatsAppMessage::class, 1);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'invoice.resent', 'subject_id' => $invoice->id, 'user_id' => $kasir->id]);
});

it('menolak kirim ulang selama pesan sebelumnya masih antre', function () {
    $invoice = resendableInvoice();
    MessageLog::factory()->for($invoice)->create(['template_key' => MessageTemplateKey::InvoiceIssued, 'status' => MessageStatus::Queued]);

    expect(fn () => app(ResendInvoice::class)->handle($invoice, User::factory()->create()))
        ->toThrow(ValidationException::class, 'Pesan tagihan sebelumnya masih dalam antrean pengiriman.');
    Queue::assertNothingPushed();
});

it('menolak kirim ulang tagihan yang sudah lunas atau dibatalkan', function (InvoiceStatus $status) {
    app(ResendInvoice::class)->handle(resendableInvoice($status), User::factory()->create());
})->with([InvoiceStatus::Paid, InvoiceStatus::Cancelled])
    ->throws(ValidationException::class, 'Hanya tagihan yang belum dibayar yang bisa dikirim ulang.');

it('menolak kirim ulang jika template tagihan nonaktif', function () {
    $invoice = resendableInvoice();
    MessageTemplate::query()->update(['is_active' => false]);

    app(ResendInvoice::class)->handle($invoice, User::factory()->create());
})->throws(ValidationException::class, 'Template pesan "Tagihan terbit" sedang nonaktif.');
