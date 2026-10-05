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
 * Notifikasi HTTP Midtrans. Tanpa Form Request: payload disimpan apa adanya (termasuk yang
 * rusak) untuk audit, dan autentikasinya adalah signature. Proses berat di queue agar
 * respons cepat; idempotensi dijaga ProcessGatewayNotification.
 */
final class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway): Response
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            return response('Payload kosong.', 400);
        }

        $notification = $this->store($payload, $gateway->verifyNotification($payload));

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
     */
    private function stringField(array $payload, string $field): string
    {
        $value = $payload[$field] ?? '';

        return is_scalar($value) ? Str::limit((string) $value, 255, '') : '';
    }
}
