<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Payments\ProcessGatewayNotification;
use App\Contracts\PaymentGateway;
use App\Exceptions\PaymentGatewayException;
use App\Models\PaymentNotification;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Memproses notifikasi webhook yang signature-nya sudah valid, di luar request agar webhook
 * cepat merespons dan galat sementara (deadlock, database sibuk) dicoba ulang oleh queue.
 */
final class ProcessPaymentNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public int $timeout = 60;

    public function __construct(
        public PaymentNotification $notification,
    ) {}

    /**
     * Payload yang tidak bisa dibaca (misalnya nominal tidak valid) tidak akan berubah jika
     * dicoba ulang, sehingga langsung digagalkan.
     */
    public function handle(PaymentGateway $gateway, ProcessGatewayNotification $process): void
    {
        if ($this->notification->processed_at !== null) {
            return;
        }

        try {
            $notification = $gateway->parseNotification($this->notification->payload);
        } catch (PaymentGatewayException $exception) {
            $this->fail($exception);

            return;
        }

        $process->handle($notification);

        $this->notification->update(['processed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gagal memproses notifikasi pembayaran.', [
            'payment_notification_id' => $this->notification->id,
            'order_id' => $this->notification->order_id,
            'error' => $exception?->getMessage(),
        ]);

        app(ActivityLogger::class)->log('payment.notification_failed', $this->notification, null, [
            'order_id' => $this->notification->order_id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
