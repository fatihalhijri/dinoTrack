<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Webhooks\MidtransWebhookController;
use App\Jobs\ProcessPaymentNotificationJob;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCharge;
use App\Models\PaymentNotification;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const MIDTRANS_WEBHOOK_URL = '/webhooks/payments/midtrans';

beforeEach(function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-test']);
    Http::preventStrayRequests();
});

/**
 * Charge dengan order_id dan nominal yang cocok dengan signedMidtransPayload().
 */
function chargeForSignedPayload(): PaymentCharge
{
    $invoice = Invoice::factory()->create(['number' => 'INV/2026/10/00001', 'total' => 150_000]);

    return PaymentCharge::factory()->for($invoice)->create();
}

it('menolak notifikasi dengan signature salah tetapi tetap menyimpannya', function () {
    Queue::fake([ProcessPaymentNotificationJob::class]);

    $this->postJson(MIDTRANS_WEBHOOK_URL, signedMidtransPayload(serverKey: 'key-palsu'))
        ->assertForbidden();

    expect(PaymentNotification::query()->sole())
        ->signature_valid->toBeFalse()
        ->order_id->toBe('INV20261000001-1')
        ->processed_at->toBeNull();
    Queue::assertNotPushed(ProcessPaymentNotificationJob::class);
});

it('hanya menyimpan field audit dari notifikasi dengan signature salah', function () {
    Queue::fake([ProcessPaymentNotificationJob::class]);
    $payload = [...signedMidtransPayload(serverKey: 'key-palsu'), 'sampah' => str_repeat('x', 5000), 'bersarang' => ['a' => 'b']];

    $this->postJson(MIDTRANS_WEBHOOK_URL, $payload)->assertForbidden();

    // toEqual: kolom JSON MySQL tidak mempertahankan urutan key.
    expect(PaymentNotification::query()->sole()->payload)->toEqual([
        'order_id' => 'INV20261000001-1',
        'status_code' => '200',
        'gross_amount' => '150000.00',
        'transaction_status' => 'settlement',
        'transaction_id' => 'trx-123',
        'settlement_time' => '2026-10-05 10:03:00',
        'fraud_status' => 'accept',
        'signature_key' => $payload['signature_key'],
    ]);
});

it('menolak body di atas batas ukuran dengan 413 tanpa menyimpannya', function () {
    Queue::fake([ProcessPaymentNotificationJob::class]);
    $payload = [...signedMidtransPayload(), 'sampah' => str_repeat('x', MidtransWebhookController::MAX_PAYLOAD_BYTES)];

    $this->postJson(MIDTRANS_WEBHOOK_URL, $payload)->assertStatus(413);

    expect(PaymentNotification::query()->count())->toBe(0);
    Queue::assertNotPushed(ProcessPaymentNotificationJob::class);
});

it('tidak melunasi invoice dari notifikasi pending sah yang statusnya diubah menjadi settlement', function () {
    $this->travelTo('2026-10-05 10:05');
    $charge = chargeForSignedPayload();
    // Signature tetap sah karena hanya mengikat order_id, status_code, dan gross_amount.
    $payload = signedMidtransPayload(['status_code' => '201', 'transaction_status' => 'settlement']);

    $this->postJson(MIDTRANS_WEBHOOK_URL, $payload)->assertOk();

    expect($charge->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and(Payment::query()->count())->toBe(0)
        ->and(PaymentNotification::query()->sole()->processed_at)->toBeNull()
        ->and(ActivityLog::query()->where('action', 'payment.notification_failed')->sole()->properties['error'])
        ->toContain('status_code "201" tidak konsisten');
});

it('menyimpan notifikasi valid, membalas 200, dan memprosesnya lewat queue tanpa session', function () {
    Queue::fake([ProcessPaymentNotificationJob::class]);
    $payload = signedMidtransPayload();

    $this->postJson(MIDTRANS_WEBHOOK_URL, $payload)
        ->assertOk()
        ->assertContent('OK')
        ->assertCookieMissing(config('session.cookie'));

    $notification = PaymentNotification::query()->sole();
    expect($notification)
        ->gateway->toBe('midtrans')
        ->order_id->toBe('INV20261000001-1')
        ->transaction_status->toBe('settlement')
        ->payload->toEqual($payload)
        ->signature_valid->toBeTrue();
    Queue::assertPushed(ProcessPaymentNotificationJob::class, fn (ProcessPaymentNotificationJob $job): bool => $job->notification->is($notification));
});

it('melunasi invoice dari notifikasi settlement', function () {
    $this->travelTo('2026-10-05 10:05');
    $charge = chargeForSignedPayload();

    $this->postJson(MIDTRANS_WEBHOOK_URL, signedMidtransPayload())->assertOk();

    expect($charge->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(Payment::query()->sole()->reference)->toBe('trx-123')
        ->and(PaymentNotification::query()->sole()->processed_at)->not->toBeNull();
});

it('tidak membuat pembayaran ganda dari notifikasi yang dikirim dua kali', function () {
    $this->travelTo('2026-10-05 10:05');
    chargeForSignedPayload();

    $this->postJson(MIDTRANS_WEBHOOK_URL, signedMidtransPayload())->assertOk();
    $this->postJson(MIDTRANS_WEBHOOK_URL, signedMidtransPayload())->assertOk();

    expect(PaymentNotification::query()->count())->toBe(2)
        ->and(Payment::query()->count())->toBe(1);
});

it('mencatat nominal yang tidak cocok sebagai pembayaran anomali', function () {
    $this->travelTo('2026-10-05 10:05');
    $charge = chargeForSignedPayload();

    $this->postJson(MIDTRANS_WEBHOOK_URL, signedMidtransPayload(['gross_amount' => '100000.00']))->assertOk();

    expect(Payment::query()->sole()->review_note)->toBe('Nominal dibayar Rp100.000 tidak sama dengan nominal charge Rp150.000.')
        ->and($charge->invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('menolak payload kosong tanpa menyimpannya', function () {
    $this->postJson(MIDTRANS_WEBHOOK_URL, [])->assertBadRequest();

    expect(PaymentNotification::query()->count())->toBe(0);
});

it('membatasi jumlah notifikasi per menit dari satu IP', function () {
    Queue::fake([ProcessPaymentNotificationJob::class]);
    $payload = signedMidtransPayload(serverKey: 'key-palsu');

    for ($i = 0; $i < AppServiceProvider::WEBHOOK_RATE_LIMIT_PER_MINUTE; $i++) {
        $this->postJson(MIDTRANS_WEBHOOK_URL, $payload)->assertForbidden();
    }

    $this->postJson(MIDTRANS_WEBHOOK_URL, $payload)->assertTooManyRequests();
});
