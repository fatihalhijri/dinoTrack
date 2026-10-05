<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\GatewayNotification;
use App\Data\PaymentChargeResult;
use App\Enums\PaymentChargeStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Midtrans Core API (QRIS dinamis) lewat HTTP client Laravel.
 *
 * Perbedaan dengan ringkasan awal di docs/05 tercatat di sana: keberhasilan dilihat dari
 * `status_code` di body (galat bisa datang sebagai HTTP 200), `qr_string` dan `expiry_time`
 * tidak dijamin ada di respons charge, dan charge memakai header `Idempotency-Key`.
 */
final class MidtransPaymentGateway implements PaymentGateway
{
    /** Zona waktu semua tanggal di respons dan notifikasi Midtrans. */
    private const string TIMEZONE = 'Asia/Jakarta';

    /** Gambar QR berbingkai ASPI lebih dulu, lalu QR polos. */
    private const array QR_ACTIONS = ['generate-qr-code-v2', 'generate-qr-code'];

    public function createQrisCharge(Invoice $invoice, string $orderId): PaymentChargeResult
    {
        $expiryMinutes = (int) config('services.midtrans.qris_expiry_minutes');

        // Idempotency-Key membuat retry setelah timeout mengembalikan respons yang sama,
        // bukan galat 406 order_id ganda.
        $body = $this->send('Membuat charge QRIS', fn (PendingRequest $http): Response => $http
            ->withHeaders(['Idempotency-Key' => $orderId])
            ->post('/v2/charge', [
                'payment_type' => 'qris',
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $invoice->total,
                ],
                'custom_expiry' => [
                    'expiry_duration' => $expiryMinutes,
                    'unit' => 'minute',
                ],
            ]));

        if (($body['status_code'] ?? null) !== '201') {
            throw $this->errorFrom('Membuat charge QRIS', $body);
        }

        $qrString = $this->optionalString($body, 'qr_string');
        $qrUrl = $this->qrImageUrl($body);

        if ($qrString === null && $qrUrl === null) {
            throw new PaymentGatewayException('Membuat charge QRIS: respons Midtrans tidak berisi QR.');
        }

        return new PaymentChargeResult(
            orderId: $orderId,
            amount: $this->parseAmount($body['gross_amount'] ?? $invoice->total),
            status: $this->mapStatus($body) ?? PaymentChargeStatus::Pending,
            qrString: $qrString,
            qrUrl: $qrUrl,
            expiresAt: $this->parseTime($body, 'expiry_time') ?? now()->addMinutes($expiryMinutes),
            rawResponse: $body,
        );
    }

    public function verifyNotification(array $payload): bool
    {
        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            return false;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key'] as $field) {
            if (! isset($payload[$field]) || ! is_string($payload[$field])) {
                return false;
            }
        }

        // gross_amount dipakai persis seperti diterima ("150000.00"), bukan hasil konversi.
        $expected = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey);

        return hash_equals($expected, strtolower($payload['signature_key']));
    }

    public function parseNotification(array $payload): GatewayNotification
    {
        $orderId = $this->optionalString($payload, 'order_id');

        if ($orderId === null) {
            throw new PaymentGatewayException('Notifikasi Midtrans tanpa order_id.');
        }

        $status = $this->mapStatus($payload);

        return new GatewayNotification(
            orderId: $orderId,
            status: $status,
            grossAmount: $this->parseAmount($payload['gross_amount'] ?? null),
            reference: $this->optionalString($payload, 'transaction_id'),
            payload: $payload,
            transactionStatus: strtolower($this->optionalString($payload, 'transaction_status') ?? ''),
            paidAt: $status === PaymentChargeStatus::Settled ? $this->parseTime($payload, 'settlement_time') : null,
        );
    }

    public function checkStatus(string $orderId): GatewayNotification
    {
        $body = $this->send('Cek status transaksi', fn (PendingRequest $http): Response => $http
            ->get('/v2/'.rawurlencode($orderId).'/status'));

        // Order yang tidak ada dikembalikan dengan HTTP 200 dan body status_code "404"; status_code
        // transaksi yang ada pun berbeda-beda (200, 201, 407, ...), jadi yang dicek transaction_status.
        if ($this->optionalString($body, 'transaction_status') === null) {
            throw $this->errorFrom('Cek status transaksi', $body);
        }

        return $this->parseNotification($body);
    }

    /**
     * @param  Closure(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function send(string $context, Closure $request): array
    {
        $serverKey = config('services.midtrans.server_key');

        if (! is_string($serverKey) || $serverKey === '') {
            throw new PaymentGatewayException("{$context}: Server Key Midtrans belum diatur (MIDTRANS_SERVER_KEY).");
        }

        $http = Http::baseUrl((string) config('services.midtrans.base_url'))
            ->withBasicAuth($serverKey, '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout((int) config('services.midtrans.connect_timeout'))
            ->timeout((int) config('services.midtrans.timeout'))
            ->retry(2, 200, fn (Throwable $exception): bool => $exception instanceof ConnectionException, throw: false);

        try {
            $response = $request($http);
        } catch (ConnectionException $exception) {
            throw new PaymentGatewayException("{$context}: tidak bisa terhubung ke Midtrans ({$exception->getMessage()}).", previous: $exception);
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new PaymentGatewayException("{$context}: respons Midtrans bukan JSON (HTTP {$response->status()}).");
        }

        /** @var array<string, mixed> $body */
        if ($response->failed()) {
            throw $this->errorFrom($context, $body, $response->status());
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorFrom(string $context, array $body, ?int $httpStatus = null): PaymentGatewayException
    {
        $code = $this->optionalString($body, 'status_code') ?? (string) $httpStatus;
        $message = $this->optionalString($body, 'status_message') ?? 'tanpa pesan';

        return new PaymentGatewayException("{$context} gagal: [{$code}] {$message}");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapStatus(array $payload): ?PaymentChargeStatus
    {
        $fraudStatus = strtolower($this->optionalString($payload, 'fraud_status') ?? '');

        return match (strtolower($this->optionalString($payload, 'transaction_status') ?? '')) {
            'settlement' => PaymentChargeStatus::Settled,
            // capture hanya untuk kartu; disertakan agar pemetaan lengkap.
            'capture' => match ($fraudStatus) {
                'accept' => PaymentChargeStatus::Settled,
                'deny' => PaymentChargeStatus::Failed,
                default => PaymentChargeStatus::Pending,
            },
            'pending', 'authorize' => PaymentChargeStatus::Pending,
            'expire' => PaymentChargeStatus::Expired,
            'cancel', 'deny', 'failure' => PaymentChargeStatus::Failed,
            default => null,
        };
    }

    /**
     * Nominal Midtrans berupa string dua desimal ("150000.00"); rupiah tidak punya sen.
     */
    private function parseAmount(mixed $value): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^(\d+)(?:\.0{1,2})?$/', $value, $matches) === 1) {
            return (int) $matches[1];
        }

        throw new PaymentGatewayException('Nominal dari Midtrans tidak valid: '.json_encode($value));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parseTime(array $payload, string $field): ?CarbonImmutable
    {
        $value = $this->optionalString($payload, $field);

        if ($value === null) {
            return null;
        }

        try {
            $time = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $value, self::TIMEZONE);
        } catch (InvalidFormatException) {
            return null;
        }

        return $time instanceof CarbonImmutable ? $time->setTimezone(config('app.timezone')) : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function qrImageUrl(array $body): ?string
    {
        $actions = is_array($body['actions'] ?? null) ? $body['actions'] : [];

        foreach (self::QR_ACTIONS as $name) {
            foreach ($actions as $action) {
                if (is_array($action) && ($action['name'] ?? null) === $name && is_string($action['url'] ?? null)) {
                    return $action['url'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function optionalString(array $payload, string $field): ?string
    {
        $value = $payload[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
