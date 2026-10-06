<?php

declare(strict_types=1);

use App\Actions\Payments\MarkInvoicePaid;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Enums\PaymentMethod;
use App\Jobs\ActivateCustomerJob;
use App\Jobs\SendPaymentConfirmationJob;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Pelanggan dengan invoice overdue lama (dibayar di test) pada hari 2026-10-20, toleransi 3 hari:
 * tunggakan lewat toleransi berarti due_at sebelum 2026-10-17.
 */
function customerWithOverdueInvoice(Customer $customer): Invoice
{
    test()->travelTo('2026-10-20 10:00');
    $subscription = billedSubscription(billingDay: 10, startsAt: '2026-08-10', customer: $customer);

    return existingInvoice($subscription, '2026-09-10', '2026-10-09', ['status' => InvoiceStatus::Overdue, 'due_at' => '2026-09-17']);
}

function payInFull(Invoice $invoice): Payment
{
    return app(MarkInvoicePaid::class)->handle($invoice, PaymentMethod::Cash, $invoice->total, now());
}

it('melunasi invoice overdue dan menjadwalkan konfirmasi pembayaran', function () {
    Queue::fake([SendPaymentConfirmationJob::class]);
    $invoice = customerWithOverdueInvoice(Customer::factory()->active()->create());

    $payment = payInFull($invoice);

    expect($invoice->fresh())
        ->status->toBe(InvoiceStatus::Paid)
        ->paid_at->equalTo(now())->toBeTrue();
    expect(ActivityLog::query()->where('action', 'payment.received')->sole()->properties)->toMatchArray([
        'invoice_number' => $invoice->number,
        'method' => 'cash',
        'amount' => 150000,
        'activation_queued' => false,
    ]);
    Queue::assertPushed(SendPaymentConfirmationJob::class, fn (SendPaymentConfirmationJob $job): bool => $job->payment->is($payment));
});

it('mengaktifkan pelanggan isolir otomatis yang tidak lagi menunggak lewat toleransi', function (?string $otherDueAt) {
    Queue::fake([ActivateCustomerJob::class]);
    $customer = Customer::factory()->isolated(IsolationReason::Overdue)->create();
    $invoice = customerWithOverdueInvoice($customer);

    if ($otherDueAt !== null) {
        existingInvoice(Subscription::query()->sole(), '2026-10-10', '2026-11-09', ['status' => InvoiceStatus::Overdue, 'due_at' => $otherDueAt]);
    }

    payInFull($invoice);

    Queue::assertPushed(ActivateCustomerJob::class, fn (ActivateCustomerJob $job): bool => $job->customer->is($customer));
})->with([
    'tanpa tagihan lain' => [null],
    'tagihan lain masih dalam toleransi' => ['2026-10-17'],
]);

it('tidak mengaktifkan pelanggan yang masih punya tunggakan lain lewat toleransi', function () {
    Queue::fake([ActivateCustomerJob::class]);
    $invoice = customerWithOverdueInvoice(Customer::factory()->isolated(IsolationReason::Overdue)->create());
    existingInvoice(Subscription::query()->sole(), '2026-10-10', '2026-11-09', ['status' => InvoiceStatus::Overdue, 'due_at' => '2026-10-16']);

    payInFull($invoice);

    Queue::assertNotPushed(ActivateCustomerJob::class);
});

it('tidak mengaktifkan pelanggan yang tidak diisolir otomatis', function (Closure $customer) {
    Queue::fake([ActivateCustomerJob::class]);
    $invoice = customerWithOverdueInvoice($customer());

    payInFull($invoice);

    Queue::assertNotPushed(ActivateCustomerJob::class);
})->with([
    'isolir manual' => fn () => Customer::factory()->isolated(IsolationReason::Manual)->create(),
    'aktif' => fn () => Customer::factory()->active()->create(),
    'berhenti' => fn () => Customer::factory()->terminated()->create(),
]);

it('tidak mengaktifkan pelanggan bila aktivasi otomatis dimatikan', function () {
    Queue::fake([ActivateCustomerJob::class]);
    Setting::query()->create(['key' => 'billing.auto_activate', 'value' => false]);
    $invoice = customerWithOverdueInvoice(Customer::factory()->isolated(IsolationReason::Overdue)->create());

    payInFull($invoice);

    Queue::assertNotPushed(ActivateCustomerJob::class);
});

it('tidak menjalankan konfirmasi dan aktivasi jika transaksi pemanggil dibatalkan', function () {
    // Queue::fake() mengabaikan afterCommit, jadi dipakai queue sync sungguhan.
    $processed = [];
    Queue::before(function (JobProcessing $event) use (&$processed): void {
        $processed[] = $event->job->resolveName();
    });
    $invoice = customerWithOverdueInvoice(Customer::factory()->isolated(IsolationReason::Overdue)->create());

    expect(fn () => DB::transaction(function () use ($invoice): void {
        payInFull($invoice);

        throw new RuntimeException('Simulasi galat.');
    }))->toThrow(RuntimeException::class);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and(Payment::query()->count())->toBe(0)
        ->and($processed)->toBe([]);
});

it('menjalankan konfirmasi dan aktivasi setelah pembayaran di-commit', function () {
    $processed = [];
    Queue::before(function (JobProcessing $event) use (&$processed): void {
        $processed[] = $event->job->resolveName();
    });
    $invoice = customerWithOverdueInvoice(Customer::factory()->isolated(IsolationReason::Overdue)->create());

    payInFull($invoice);

    expect($processed)->toBe([SendPaymentConfirmationJob::class, ActivateCustomerJob::class]);
});
