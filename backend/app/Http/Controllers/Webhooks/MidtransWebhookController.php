<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\CreateQrisCharge;
use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentNotificationJob;
use App\Models\PaymentNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifikasi HTTP Midtrans. Tanpa Form Request: autentikasinya adalah signature. Payload valid
 * disimpan utuh; payload dengan signature salah hanya disimpan field auditnya, dan body di atas
 * batas ukuran ditolak tanpa disimpan (endpoint ini publik). Proses berat di queue agar respons
 * cepat; idempotensi dijaga ProcessGatewayNotification.
 */
final class MidtransWebhookController extends Controller
{
    /** Notifikasi Midtrans sekitar 1–2 KB; batas ini mencegah endpoint publik dipakai mengisi disk. */
    public const int MAX_PAYLOAD_BYTES = 16 * 1024;

    /**
     * Field yang disimpan dari notifikasi dengan signature salah: cukup untuk audit, tanpa
     * memberi penyerang tempat menyimpan data sembarang.
     */
    private const array AUDIT_FIELDS = [
        'order_id', 'status_code', 'gross_amount', 'transaction_status', 'transaction_id',
        'transaction_time', 'settlement_time', 'payment_type', 'fraud_status', 'signature_key',
    ];

    public function __invoke(Request $request, PaymentGateway $gateway): Response
    {
        if (strlen($request->getContent()) > self::MAX_PAYLOAD_BYTES) {
            Log::warning('Notifikasi Midtrans melebihi batas ukuran ditolak tanpa disimpan.', [
                'bytes' => strlen($request->getContent()),
                'ip' => $request->ip(),
            ]);

            return response('Payload terlalu besar.', 413);
        }

        $payload = $request->json()->all();

        if ($payload === []) {
            return response('Payload kosong.', 400);
        }

        $signatureValid = $gateway->verifyNotification($payload);
        $notification = $this->store($signatureValid ? $payload : $this->auditPayload($payload), $signatureValid);

        if (! $notification->signature_valid) {
            Log::warning('Notifikasi Midtrans dengan signature tidak valid ditolak.', [
                'payment_notification_id' => $notification->id,
                'order_id' => $notification->order_id,
                'ip' => $request->ip(),
            ]);

            return response('Signature tidak valid.', 403);
        }

        ProcessPaymentNotificationJob::dispatch($notification);

        return response('OK');
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function store(array $payload, bool $signatureValid): PaymentNotification
    {
        return PaymentNotification::query()->create([
            'gateway' => CreateQrisCharge::GATEWAY,
            'order_id' => $this->stringField($payload, 'order_id'),
            'transaction_status' => $this->stringField($payload, 'transaction_status'),
            'payload' => $payload,
            'signature_valid' => $signatureValid,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, string>
     */
    private function auditPayload(array $payload): array
    {
        $fields = [];

        foreach (self::AUDIT_FIELDS as $field) {
            if (array_key_exists($field, $payload)) {
                $fields[$field] = $this->stringField($payload, $field);
            }
        }

        return $fields;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function stringField(array $payload, string $field): string
    {
        $value = $payload[$field] ?? '';

        return is_scalar($value) ? Str::limit((string) $value, 255, '') : '';
    }
}
