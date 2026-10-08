<?php

declare(strict_types=1);

use App\Actions\Notifications\NotifyCustomer;
use App\Enums\MessageStatus;
use App\Enums\MessageTemplateKey;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

function notifiedInvoice(): Invoice
{
    $customer = customerOnProfile(Customer::factory()->active()->state(['name' => 'Budi Santoso', 'phone' => '6281234567890']));

    return invoiceDueAt($customer, '2026-10-12');
}

it('mencatat pesan berstatus queued lalu menjadwalkan pengirimannya', function () {
    Queue::fake([SendWhatsAppMessage::class]);
    MessageTemplate::factory()->create(['key' => MessageTemplateKey::InvoiceIssued, 'body' => 'Halo {nama}, tagihan {nomor_invoice}']);
    $invoice = notifiedInvoice();

    $messageLog = app(NotifyCustomer::class)->handle($invoice->customer, MessageTemplateKey::InvoiceIssued, $invoice);

    expect($messageLog)->not->toBeNull()
        ->and($messageLog->only(['customer_id', 'invoice_id', 'template_key', 'phone', 'body', 'status']))->toBe([
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'template_key' => MessageTemplateKey::InvoiceIssued,
            'phone' => '6281234567890',
            'body' => "Halo Budi Santoso, tagihan {$invoice->number}",
            'status' => MessageStatus::Queued,
        ]);
    Queue::assertPushed(SendWhatsAppMessage::class, fn (SendWhatsAppMessage $job): bool => $job->messageLog->is($messageLog));
});

it('mengirim pesan ke nomor pelanggan lewat queue', function () {
    $this->seed(MessageTemplateSeeder::class);
    $messages = fakeMessages();
    $invoice = notifiedInvoice();

    $messageLog = app(NotifyCustomer::class)->handle($invoice->customer, MessageTemplateKey::InvoiceIssued, $invoice);

    expect($messageLog->refresh()->status)->toBe(MessageStatus::Sent)
        ->and($messages->sentMessages())->toBe([['phone' => '6281234567890', 'message' => $messageLog->body]]);
});

it('tidak membuat pesan ganda untuk kejadian dan invoice yang sama', function (MessageStatus $existingStatus) {
    $this->seed(MessageTemplateSeeder::class);
    $messages = fakeMessages();
    $invoice = notifiedInvoice();
    MessageLog::factory()->for($invoice->customer)->for($invoice)->create([
        'template_key' => MessageTemplateKey::InvoiceIssued,
        'status' => $existingStatus,
    ]);

    $messageLog = app(NotifyCustomer::class)->handle($invoice->customer, MessageTemplateKey::InvoiceIssued, $invoice);

    expect($messageLog)->toBeNull()
        ->and(MessageLog::query()->count())->toBe(1);
    $messages->assertNotCalled('send');
})->with([MessageStatus::Queued, MessageStatus::Sent]);

it('boleh mengirim lagi jika pesan sebelumnya gagal', function () {
    $this->seed(MessageTemplateSeeder::class);
    $invoice = notifiedInvoice();
    MessageLog::factory()->failed()->for($invoice->customer)->for($invoice)->create(['template_key' => MessageTemplateKey::InvoiceIssued]);

    $messageLog = app(NotifyCustomer::class)->handle($invoice->customer, MessageTemplateKey::InvoiceIssued, $invoice);

    expect($messageLog?->refresh()->status)->toBe(MessageStatus::Sent);
});

it('tetap mengirim kejadian lain atau invoice lain untuk pelanggan yang sama', function () {
    $this->seed(MessageTemplateSeeder::class);
    $invoice = notifiedInvoice();
    $otherInvoice = invoiceDueAt($invoice->customer, '2026-11-12');
    MessageLog::factory()->for($invoice->customer)->for($invoice)->create(['template_key' => MessageTemplateKey::InvoiceIssued]);
    $notify = app(NotifyCustomer::class);

    expect($notify->handle($invoice->customer, MessageTemplateKey::ReminderDue, $invoice))->not->toBeNull()
        ->and($notify->handle($invoice->customer, MessageTemplateKey::InvoiceIssued, $otherInvoice))->not->toBeNull();
});

it('tidak mencatat atau mengirim apa pun jika template dinonaktifkan', function () {
    MessageTemplate::factory()->create(['key' => MessageTemplateKey::InvoiceIssued, 'is_active' => false]);
    $messages = fakeMessages();
    $invoice = notifiedInvoice();

    expect(app(NotifyCustomer::class)->handle($invoice->customer, MessageTemplateKey::InvoiceIssued, $invoice))->toBeNull()
        ->and(MessageLog::query()->count())->toBe(0);
    $messages->assertNotCalled('send');
});

it('tidak mengirim pesan jika transaksi pemanggil dibatalkan', function () {
    $this->seed(MessageTemplateSeeder::class);
    $messages = fakeMessages();
    $invoice = notifiedInvoice();

    expect(fn () => DB::transaction(function () use ($invoice): void {
        app(NotifyCustomer::class)->handle($invoice->customer, MessageTemplateKey::InvoiceIssued, $invoice);

        throw new RuntimeException('Simulasi galat setelah notifikasi.');
    }))->toThrow(RuntimeException::class);

    expect(MessageLog::query()->count())->toBe(0);
    $messages->assertNotCalled('send');
});
