<?php

declare(strict_types=1);

use App\Enums\PaymentChargeStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Services\Payment\MidtransPaymentGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const SANDBOX_CHARGE_URL = 'https://api.sandbox.midtrans.com/v2/charge';

beforeEach(function () {
    config(['services.midtrans.server_key' => 'SB-Mid-server-test']);
    Http::preventStrayRequests();
    Sleep::fake();
});

/**
 * Respons charge QRIS sesuai contoh dokumentasi resmi Midtrans.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function midtransChargeResponse(array $overrides = []): array
{
    return [
        'status_code' => '201',
        'status_message' => 'QRIS transaction is created',
        'transaction_id' => '0d8178e1-0000-0000-0000-000000000001',
        'order_id' => 'INV20261000001-1',
        'gross_amount' => '150000.00',
        'currency' => 'IDR',
        'payment_type' => 'qris',
        'transaction_time' => '2026-10-05 10:00:00',
        'transaction_status' => 'pending',
        'fraud_status' => 'accept',
        'acquirer' => 'gopay',
        'actions' => [
            ['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v2/qris/abc/qr-code'],
            ['name' => 'generate-qr-code-v2', 'method' => 'GET', 'url' => 'https://api.sandbox.midtrans.com/v4/qris/abc/qr-code'],
        ],
        ...$overrides,
    ];
}

function invoiceForCharge(int $total = 150_000): Invoice
{
    return (new Invoice)->forceFill(['id' => 1, 'number' => 'INV/2026/10/00001', 'total' => $total]);
}

it('mengirim charge QRIS ke sandbox dengan Basic Auth, nominal, kedaluwarsa, dan Idempotency-Key', function () {
    Http::fake([SANDBOX_CHARGE_URL => Http::response(midtransChargeResponse())]);

    app(MidtransPaymentGateway::class)->createQrisCharge(invoiceForCharge(), 'INV20261000001-1');

    Http::assertSent(fn (Request $request): bool => $request->url() === SANDBOX_CHARGE_URL
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-test:'))
        && $request->hasHeader('Idempotency-Key', 'INV20261000001-1')
        && $request->data() === [
            'payment_type' => 'qris',
            'transaction_details' => ['order_id' => 'INV20261000001-1', 'gross_amount' => 150000],
            'custom_expiry' => ['expiry_duration' => 15, 'unit' => 'minute'],
        ]);
});

it('memakai URL production bila MIDTRANS_IS_PRODUCTION aktif', function () {
    config(['services.midtrans.base_url' => 'https://api.midtrans.com']);
    Http::fake(['https://api.midtrans.com/v2/charge' => Http::response(midtransChargeResponse())]);

    app(MidtransPaymentGateway::class)->createQrisCharge(invoiceForCharge(), 'INV20261000001-1');

    Http::assertSentCount(1);
});

it('membaca QR berbingkai, nominal, dan kedaluwarsa dari respons charge', function () {
    $this->freezeSecond();
    Http::fake([SANDBOX_CHARGE_URL => Http::response(midtransChargeResponse())]);

    $result = app(MidtransPaymentGateway::class)->createQrisCharge(invoiceForCharge(), 'INV20261000001-1');

    expect($result->orderId)->toBe('INV20261000001-1')
        ->and($result->amount)->toBe(150000)
        ->and($result->status)->toBe(PaymentChargeStatus::Pending)
        ->and($result->qrUrl)->toBe('https://api.sandbox.midtrans.com/v4/qris/abc/qr-code')
        ->and($result->qrString)->toBeNull()
        ->and($result->expiresAt->equalTo(now()->addMinutes(15)))->toBeTrue()
        ->and($result->rawResponse['transaction_id'])->toBe('0d8178e1-0000-0000-0000-000000000001');
});

it('memakai qr_string dan expiry_time bila respons menyertakannya', function () {
    Http::fake([SANDBOX_CHARGE_URL => Http::response(midtransChargeResponse([
        'qr_string' => '00020101021226...',
        'expiry_time' => '2026-10-05 10:15:00',
        'actions' => [['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://qr.test/polos.png']],
    ]))]);

    $result = app(MidtransPaymentGateway::class)->createQrisCharge(invoiceForCharge(), 'INV20261000001-1');

    expect($result->qrString)->toBe('00020101021226...')
        ->and($result->qrUrl)->toBe('https://qr.test/polos.png')
        ->and($result->expiresAt->format('Y-m-d H:i:s'))->toBe('2026-10-05 10:15:00');
});

it('melempar galat dengan kode Midtrans saat charge ditolak', function (int $httpStatus, array|string $body, string $message) {
    Http::fake([SANDBOX_CHARGE_URL => Http::response($body, $httpStatus)]);

    expect(fn () => app(MidtransPaymentGateway::class)->createQrisCharge(invoiceForCharge(), 'INV20261000001-1'))
        ->toThrow(PaymentGatewayException::class, $message);
})->with([
    'order_id ganda dalam body HTTP 200' => [200, ['status_code' => '406', 'status_message' => 'Duplicate order ID'], '[406] Duplicate order ID'],
    'Server Key salah' => [401, ['status_code' => '401', 'status_message' => 'Unauthorized'], '[401] Unauthorized'],
    'galat server tanpa JSON' => [500, 'Internal Server Error', 'bukan JSON (HTTP 500)'],
    'respons tanpa QR' => [200, ['status_code' => '201', 'gross_amount' => '150000.00', 'actions' => []], 'tidak berisi QR'],
]);

it('mencoba ulang lalu melempar galat saat Midtrans tidak bisa dihubungi', function () {
    Http::fake([SANDBOX_CHARGE_URL => Http::failedConnection('timeout')]);

    expect(fn () => app(MidtransPaymentGateway::class)->createQrisCharge(invoiceForCharge(), 'INV20261000001-1'))
        ->toThrow(PaymentGatewayException::class, 'tidak bisa terhubung ke Midtrans');

    Http::assertSentCount(2);
});

it('tidak memanggil Midtrans bila Server Key belum diatur', function () {
    config(['services.midtrans.server_key' => null]);

    expect(fn () => app(MidtransPaymentGateway::class)->createQrisCharge(invoiceForCharge(), 'INV20261000001-1'))
        ->toThrow(PaymentGatewayException::class, 'Server Key Midtrans belum diatur');

    Http::assertNothingSent();
});

it('menerima notifikasi dengan signature yang benar', function () {
    expect(app(MidtransPaymentGateway::class)->verifyNotification(signedMidtransPayload()))->toBeTrue();
});

it('menolak notifikasi dengan signature yang salah atau tidak lengkap', function (Closure $payload) {
    expect(app(MidtransPaymentGateway::class)->verifyNotification($payload()))->toBeFalse();
})->with([
    'ditandatangani key lain' => fn () => signedMidtransPayload(serverKey: 'key-lain'),
    'nominal diubah setelah ditandatangani' => fn () => [...signedMidtransPayload(), 'gross_amount' => '1.00'],
    'tanpa signature_key' => fn () => array_diff_key(signedMidtransPayload(), ['signature_key' => true]),
    'order_id bukan string' => fn () => [...signedMidtransPayload(), 'order_id' => ['x']],
]);

it('menolak semua notifikasi bila Server Key belum diatur', function () {
    $payload = signedMidtransPayload(serverKey: '');
    config(['services.midtrans.server_key' => '']);

    expect(app(MidtransPaymentGateway::class)->verifyNotification($payload))->toBeFalse();
});

it('memetakan status transaksi Midtrans ke status charge', function (string $transactionStatus, ?string $fraudStatus, ?PaymentChargeStatus $expected) {
    $notification = app(MidtransPaymentGateway::class)->parseNotification(
        signedMidtransPayload(['transaction_status' => $transactionStatus, 'fraud_status' => $fraudStatus]),
    );

    expect($notification->status)->toBe($expected)
        ->and($notification->transactionStatus)->toBe(strtolower($transactionStatus));
})->with([
    'settlement' => ['settlement', 'accept', PaymentChargeStatus::Settled],
    'huruf besar' => ['SETTLEMENT', null, PaymentChargeStatus::Settled],
    'capture accept' => ['capture', 'accept', PaymentChargeStatus::Settled],
    'capture challenge' => ['capture', 'challenge', PaymentChargeStatus::Pending],
    'pending' => ['pending', null, PaymentChargeStatus::Pending],
    'expire' => ['expire', null, PaymentChargeStatus::Expired],
    'cancel' => ['cancel', null, PaymentChargeStatus::Failed],
    'deny' => ['deny', null, PaymentChargeStatus::Failed],
    'failure' => ['failure', null, PaymentChargeStatus::Failed],
    'refund' => ['refund', null, null],
    'status baru yang tidak dikenal' => ['mystery', null, null],
]);

it('menolak status lunas yang tidak datang dengan status_code 200', function (string $transactionStatus, string $statusCode) {
    $payload = signedMidtransPayload(['transaction_status' => $transactionStatus, 'status_code' => $statusCode]);

    expect(fn () => app(MidtransPaymentGateway::class)->parseNotification($payload))
        ->toThrow(PaymentGatewayException::class, 'tidak konsisten');
})->with([
    'settlement dari payload pending' => ['settlement', '201'],
    'settlement dari payload expire' => ['settlement', '407'],
    'capture dari payload deny' => ['capture', '202'],
]);

it('membaca nominal, referensi, dan waktu lunas dari notifikasi', function () {
    $notification = app(MidtransPaymentGateway::class)->parseNotification(signedMidtransPayload());

    expect($notification->orderId)->toBe('INV20261000001-1')
        ->and($notification->grossAmount)->toBe(150000)
        ->and($notification->reference)->toBe('trx-123')
        ->and($notification->paidAt?->format('Y-m-d H:i:s'))->toBe('2026-10-05 10:03:00');
});

it('menolak notifikasi dengan nominal pecahan atau tanpa order_id', function (array $overrides, string $message) {
    expect(fn () => app(MidtransPaymentGateway::class)->parseNotification([...signedMidtransPayload(), ...$overrides]))
        ->toThrow(PaymentGatewayException::class, $message);
})->with([
    'nominal pecahan' => [['gross_amount' => '150000.50'], 'Nominal dari Midtrans tidak valid'],
    'tanpa order_id' => [['order_id' => ''], 'tanpa order_id'],
]);

it('mengecek status transaksi ke Midtrans', function () {
    Http::fake(['https://api.sandbox.midtrans.com/v2/INV20261000001-1/status' => Http::response(
        [...signedMidtransPayload(['transaction_status' => 'expire', 'status_code' => '407']), 'status_message' => 'Success'],
    )]);

    $notification = app(MidtransPaymentGateway::class)->checkStatus('INV20261000001-1');

    expect($notification->status)->toBe(PaymentChargeStatus::Expired)
        ->and($notification->grossAmount)->toBe(150000);
});

it('melempar galat saat order tidak ditemukan meskipun HTTP 200', function () {
    Http::fake(['https://api.sandbox.midtrans.com/v2/INV20261000001-9/status' => Http::response(
        ['status_code' => '404', 'status_message' => "Transaction doesn't exist."],
    )]);

    expect(fn () => app(MidtransPaymentGateway::class)->checkStatus('INV20261000001-9'))
        ->toThrow(PaymentGatewayException::class, "[404] Transaction doesn't exist.");
});

it('mengubah galat koneksi saat cek status menjadi galat gateway', function () {
    Http::fake(['https://api.sandbox.midtrans.com/*' => fn () => throw new ConnectionException('timeout')]);

    expect(fn () => app(MidtransPaymentGateway::class)->checkStatus('INV20261000001-1'))
        ->toThrow(PaymentGatewayException::class, 'tidak bisa terhubung');
});
