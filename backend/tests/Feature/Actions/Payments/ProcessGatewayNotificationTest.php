<?php

declare(strict_types=1);

use App\Actions\Payments\ProcessGatewayNotification;
use App\Data\GatewayNotification;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Jobs\SendPaymentConfirmationJob;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCharge;
use Carbon\CarbonImmutable;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

it('melunasi invoice dari notifikasi settlement', function () {
    Queue::fake([SendPaymentConfirmationJob::class]);
    $charge = PaymentCharge::factory()->create();

    app(ProcessGatewayNotification::class)->handle(
        gatewayNotification($charge, reference: 'trx-123', paidAt: CarbonImmutable::parse('2026-10-05 10:03:00')),
    );

    expect(Payment::query()->sole())
        ->invoice_id->toBe($charge->invoice_id)
        ->payment_charge_id->toBe($charge->id)
        ->method->toBe(PaymentMethod::Qris)
        ->amount->toBe($charge->amount)
        ->reference->toBe('trx-123')
        ->received_by->toBeNull()
        ->review_status->toBe(PaymentReviewStatus::None)
        ->paid_at->format('Y-m-d H:i:s')->toBe('2026-10-05 10:03:00');
    expect($charge->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($charge->fresh()->status)->toBe(PaymentChargeStatus::Settled);
    Queue::assertPushed(SendPaymentConfirmationJob::class);
});

it('tidak membuat pembayaran ganda dari notifikasi yang sama', function () {
    $charge = PaymentCharge::factory()->create();

    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge));
    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge));

    expect(Payment::query()->count())->toBe(1);
});

it('mencatat pembayaran anomali tanpa mengubah invoice', function (Closure $arrange, InvoiceStatus $invoiceStatus, ?int $grossAmount, string $reason) {
    Queue::fake([SendPaymentConfirmationJob::class]);
    $charge = $arrange();

    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge, grossAmount: $grossAmount));

    expect(Payment::query()->sole())
        ->review_status->toBe(PaymentReviewStatus::NeedsReview)
        ->review_note->toBe($reason)
        ->amount->toBe($grossAmount ?? $charge->amount)
        ->payment_charge_id->toBe($charge->id);
    expect($charge->invoice->fresh()->status)->toBe($invoiceStatus)
        ->and($charge->fresh()->status)->toBe(PaymentChargeStatus::Settled)
        ->and(ActivityLog::query()->where('action', 'payment.needs_review')->sole()->properties['reason'])->toBe($reason);
    Queue::assertNotPushed(SendPaymentConfirmationJob::class);
})->with([
    'nominal tidak cocok' => [
        fn () => PaymentCharge::factory()->for(Invoice::factory()->state(['total' => 150_000]))->create(),
        InvoiceStatus::Unpaid,
        100_000,
        'Nominal dibayar Rp100.000 tidak sama dengan nominal charge Rp150.000.',
    ],
    'invoice sudah lunas tunai' => [
        fn () => PaymentCharge::factory()->for(Invoice::factory()->paid())->create(),
        InvoiceStatus::Paid,
        null,
        'Tagihan sudah lunas sebelum pembayaran QRIS ini masuk.',
    ],
    'invoice dibatalkan' => [
        fn () => PaymentCharge::factory()->for(Invoice::factory()->cancelled())->create(),
        InvoiceStatus::Cancelled,
        null,
        'Tagihan sudah dibatalkan sebelum pembayaran QRIS ini masuk.',
    ],
]);

it('menerapkan normal pembayaran untuk charge yang sudah kedaluwarsa selama invoice masih terbuka', function () {
    $charge = PaymentCharge::factory()->expired()->create();

    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge));

    expect(Payment::query()->sole()->review_status)->toBe(PaymentReviewStatus::None)
        ->and($charge->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($charge->fresh()->status)->toBe(PaymentChargeStatus::Settled);
});

it('menutup charge pending yang kedaluwarsa atau gagal tanpa mengubah invoice', function (PaymentChargeStatus $status, string $transactionStatus) {
    $charge = PaymentCharge::factory()->create();

    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge, $status, transactionStatus: $transactionStatus));

    expect($charge->fresh()->status)->toBe($status)
        ->and($charge->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and(Payment::query()->count())->toBe(0);
})->with([
    'expire' => [PaymentChargeStatus::Expired, 'expire'],
    'deny' => [PaymentChargeStatus::Failed, 'deny'],
    'cancel' => [PaymentChargeStatus::Failed, 'cancel'],
    'failure' => [PaymentChargeStatus::Failed, 'failure'],
]);

it('mengabaikan notifikasi expire yang datang setelah settlement', function () {
    $charge = PaymentCharge::factory()->create();
    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge));

    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge, PaymentChargeStatus::Expired, transactionStatus: 'expire'));

    expect($charge->fresh()->status)->toBe(PaymentChargeStatus::Settled)
        ->and($charge->invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('tidak mengubah apa pun untuk notifikasi pending', function () {
    $charge = PaymentCharge::factory()->create();

    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge, PaymentChargeStatus::Pending, transactionStatus: 'pending'));

    expect($charge->fresh()->status)->toBe(PaymentChargeStatus::Pending)
        ->and(Payment::query()->count())->toBe(0);
});

it('mengabaikan notifikasi untuk order_id yang tidak dikenal', function () {
    $invoice = Invoice::factory()->create();

    app(ProcessGatewayNotification::class)->handle(new GatewayNotification('payment_notif_test_1', PaymentChargeStatus::Settled, 10_000));

    expect(Payment::query()->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('hanya mencatat refund dari gateway tanpa mengubah pembayaran', function () {
    $charge = PaymentCharge::factory()->create();
    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge));

    app(ProcessGatewayNotification::class)->handle(gatewayNotification($charge, null, transactionStatus: 'refund'));

    expect($charge->fresh()->status)->toBe(PaymentChargeStatus::Settled)
        ->and($charge->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(Payment::query()->sole()->review_status)->toBe(PaymentReviewStatus::None);
    expect(ActivityLog::query()->where('action', 'payment.gateway_reversal')->sole())
        ->subject_id->toBe($charge->id)
        ->properties->toMatchArray(['transaction_status' => 'refund', 'order_id' => $charge->order_id]);
});

it('membuat satu pembayaran saat beberapa notifikasi diproses bersamaan', function () {
    $processes = 4;

    // Proses anak tidak melihat transaksi RefreshDatabase, sehingga data dibuat lewat koneksi
    // terpisah yang langsung commit lalu dibersihkan di akhir test.
    config(['database.connections.concurrent' => config('database.connections.mysql')]);
    $concurrent = DB::connection('concurrent');
    DB::setDefaultConnection('concurrent');

    try {
        $charge = PaymentCharge::factory()->create();
    } finally {
        DB::setDefaultConnection('mysql');
    }

    // Semua proses menunggu sampai detik yang sama agar panggilan benar-benar bersamaan.
    $code = sprintf(
        'time_sleep_until(%F); try { app(%s::class)->handle(new %s("%s", %s::Settled, %d, "trx-race")); echo "OK"; } catch (Throwable $e) { echo PHP_EOL."GALAT ".$e->getMessage().PHP_EOL; }',
        microtime(true) + 6,
        ProcessGatewayNotification::class,
        GatewayNotification::class,
        $charge->order_id,
        PaymentChargeStatus::class,
        $charge->amount,
    );

    try {
        $results = Process::concurrently(function (Pool $pool) use ($processes, $code): void {
            for ($i = 0; $i < $processes; $i++) {
                $pool->path(base_path())
                    ->env([
                        'APP_ENV' => 'testing',
                        'DB_CONNECTION' => 'mysql',
                        'DB_DATABASE' => config('database.connections.mysql.database'),
                        'QUEUE_CONNECTION' => 'sync',
                        'CACHE_STORE' => 'array',
                    ])
                    ->timeout(120)
                    ->command([PHP_BINARY, 'artisan', 'tinker', '--execute', $code]);
            }
        });

        $output = collect($results)->map(fn ($result) => $result->output().$result->errorOutput())->implode('');

        expect($output)->not->toContain('GALAT')
            ->and(substr_count($output, 'OK'))->toBe($processes)
            ->and($concurrent->table('payments')->where('payment_charge_id', $charge->id)->count())->toBe(1)
            ->and($concurrent->table('invoices')->where('id', $charge->invoice_id)->value('status'))->toBe(InvoiceStatus::Paid->value);
    } finally {
        $invoice = $concurrent->table('invoices')->where('id', $charge->invoice_id)->first();
        $subscription = $concurrent->table('subscriptions')->where('id', $invoice->subscription_id)->first();
        $customer = $concurrent->table('customers')->where('id', $invoice->customer_id)->first();
        $paymentIds = $concurrent->table('payments')->where('invoice_id', $invoice->id)->pluck('id');

        $concurrent->table('activity_logs')->where('subject_type', 'payment')->whereIn('subject_id', $paymentIds)->delete();
        $concurrent->table('payments')->whereIn('id', $paymentIds)->delete();
        $concurrent->table('payment_charges')->where('invoice_id', $invoice->id)->delete();
        $concurrent->table('invoice_items')->where('invoice_id', $invoice->id)->delete();
        $concurrent->table('invoices')->where('id', $invoice->id)->delete();
        $concurrent->table('subscriptions')->where('id', $subscription->id)->delete();
        $concurrent->table('customers')->where('id', $customer->id)->delete();
        $concurrent->table('packages')->where('id', $subscription->package_id)->delete();
        $concurrent->table('routers')->where('id', $customer->router_id)->delete();
        DB::purge('concurrent');
    }
});
