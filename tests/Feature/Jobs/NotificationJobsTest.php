<?php

declare(strict_types=1);

use App\Actions\Invoices\GenerateMonthlyInvoices;
use App\Actions\Network\IsolateCustomer;
use App\Actions\Payments\MarkInvoicePaid;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Enums\MessageTemplateKey;
use App\Enums\PaymentMethod;
use App\Jobs\SendInvoiceNotificationJob;
use App\Jobs\SendIsolationNotificationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageLog;
use Carbon\CarbonImmutable;
use Database\Seeders\MessageTemplateSeeder;

beforeEach(function () {
    $this->seed(MessageTemplateSeeder::class);
});

/**
 * @return list<MessageTemplateKey|null>
 */
function sentTemplateKeys(): array
{
    return MessageLog::query()->orderBy('id')->get()->map(fn (MessageLog $log) => $log->template_key)->all();
}

it('mengirim pesan tagihan terbit untuk invoice dari generator', function () {
    $messages = fakeMessages();
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-08-10');
    $subscription->customer->update(['phone' => '6281234567890']);
    existingInvoice($subscription, '2026-09-10', '2026-10-09');

    app(GenerateMonthlyInvoices::class)->handle(CarbonImmutable::parse('2026-10-10'));

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    expect(sentTemplateKeys())->toBe([MessageTemplateKey::InvoiceIssued])
        ->and($messages->sentMessages())->toHaveCount(1)
        ->and($messages->sentMessages()[0]['phone'])->toBe('6281234567890')
        ->and($messages->sentMessages()[0]['message'])->toContain($invoice->number);
});

it('tidak mengirim pesan tagihan terbit jika invoice sudah lunas atau dibatalkan sebelum job berjalan', function (InvoiceStatus $status) {
    $messages = fakeMessages();
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-12', $status);

    SendInvoiceNotificationJob::dispatch($invoice);

    $messages->assertNotCalled('send');
    expect(MessageLog::query()->count())->toBe(0);
})->with([InvoiceStatus::Paid, InvoiceStatus::Cancelled]);

it('mengirim konfirmasi pembayaran setelah invoice dilunasi', function () {
    $this->travelTo('2026-10-08 10:00');
    $invoice = invoiceDueAt(customerOnProfile(Customer::factory()->active()), '2026-10-12', InvoiceStatus::Unpaid);
    $messages = fakeMessages();

    app(MarkInvoicePaid::class)->handle($invoice, PaymentMethod::Cash, $invoice->total, now());

    expect(sentTemplateKeys())->toBe([MessageTemplateKey::PaymentReceived])
        ->and($messages->sentMessages()[0]['message'])->toContain($invoice->number);
});

it('mengirim pemberitahuan isolir yang menyebut tunggakan lewat toleransi paling lama', function () {
    $this->travelTo('2026-10-20 01:15');
    $customer = customerOnProfile(Customer::factory()->active());
    $oldest = invoiceDueAt($customer, '2026-09-05');
    invoiceDueAt($customer, '2026-10-05');
    $messages = fakeMessages();

    app(IsolateCustomer::class)->handle($customer, IsolationReason::Overdue, today());

    expect(sentTemplateKeys())->toBe([MessageTemplateKey::Isolated])
        ->and(MessageLog::query()->value('invoice_id'))->toBe($oldest->id)
        ->and($messages->sentMessages()[0]['message'])->toContain($oldest->number);
});

it('tidak mengirim pemberitahuan isolir untuk isolir manual', function () {
    $this->travelTo('2026-10-20 10:00');
    $customer = customerOnProfile(Customer::factory()->active());
    invoiceDueAt($customer, '2026-09-05');
    $messages = fakeMessages();

    app(IsolateCustomer::class)->handle($customer, IsolationReason::Manual, today(), note: 'Pelanggaran ketentuan');

    $messages->assertNotCalled('send');
});

it('tidak mengirim pemberitahuan isolir jika pelanggan sudah aktif atau tunggakannya sudah lunas saat job berjalan', function (bool $isStillIsolated) {
    $this->travelTo('2026-10-20 10:00');
    $customer = customerOnProfile($isStillIsolated ? Customer::factory()->isolated() : Customer::factory()->active());
    invoiceDueAt($customer, '2026-09-05', InvoiceStatus::Paid);
    $messages = fakeMessages();

    SendIsolationNotificationJob::dispatch($customer);

    $messages->assertNotCalled('send');
})->with(['sudah aktif kembali' => false, 'masih isolir tetapi sudah lunas' => true]);
