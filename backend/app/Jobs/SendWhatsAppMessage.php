<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MessageSender;
use App\Enums\MessageStatus;
use App\Exceptions\MessageSendException;
use App\Models\MessageLog;
use App\Support\ActivityLogger;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim satu pesan dari `message_logs` (queued → sent/failed).
 *
 * Middleware RateLimited menahan pesan agar tidak lebih dari satu per beberapa detik; setiap
 * penahanan dihitung sebagai percobaan, sehingga batasnya memakai `retryUntil()` (antrean panjang
 * di hari tagih tidak kehabisan percobaan) dan kegagalan sungguhan dibatasi `$maxExceptions`.
 * Penolakan provider (nomor tidak valid, token salah) langsung `failed` tanpa dicoba ulang.
 */
final class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 30;

    public bool $deleteWhenMissingModels = true;

    public const int RETRY_HOURS = 6;

    public function __construct(
        public MessageLog $messageLog,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp')];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(self::RETRY_HOURS);
    }

    /**
     * @throws MessageSendException galat sementara; dicoba ulang queue
     */
    public function handle(MessageSender $sender, ActivityLogger $logger): void
    {
        // Percobaan ulang setelah pesan terkirim (misalnya worker mati setelah mengirim) tidak mengirim lagi.
        if ($this->messageLog->status !== MessageStatus::Queued) {
            return;
        }

        $result = $sender->send($this->messageLog->phone, $this->messageLog->body);

        if ($result->success) {
            $this->messageLog->update([
                'status' => MessageStatus::Sent,
                'provider_message_id' => $result->providerMessageId,
                'error' => null,
                'sent_at' => now(),
            ]);
            $logger->log('message.sent', $this->messageLog, properties: $this->logProperties());

            return;
        }

        $this->markFailed($result->error ?? 'Pesan ditolak provider.', $logger);
    }

    public function failed(?Throwable $exception): void
    {
        $messageLog = MessageLog::query()->find($this->messageLog->id);

        if ($messageLog === null || $messageLog->status !== MessageStatus::Queued) {
            return;
        }

        $this->messageLog = $messageLog;
        $this->markFailed($exception?->getMessage() ?? 'Job gagal tanpa pesan galat.', app(ActivityLogger::class));
    }

    private function markFailed(string $error, ActivityLogger $logger): void
    {
        $this->messageLog->update(['status' => MessageStatus::Failed, 'error' => $error]);

        $logger->log('message.failed', $this->messageLog, properties: [...$this->logProperties(), 'error' => $error]);

        Log::error('Gagal mengirim pesan WhatsApp.', [
            'message_log_id' => $this->messageLog->id,
            'customer_id' => $this->messageLog->customer_id,
            'invoice_id' => $this->messageLog->invoice_id,
            'error' => $error,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function logProperties(): array
    {
        return [
            'template_key' => $this->messageLog->template_key?->value,
            'invoice_id' => $this->messageLog->invoice_id,
            'customer_id' => $this->messageLog->customer_id,
        ];
    }
}
