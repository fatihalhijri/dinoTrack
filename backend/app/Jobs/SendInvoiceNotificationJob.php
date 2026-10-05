<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim pesan WhatsApp `invoice_issued` untuk invoice yang baru terbit.
 * Pengiriman diisi di Tahap 07 (MessageSender + message_logs); untuk saat ini job sudah
 * di-dispatch agar alur Tahap 04 tidak perlu diubah lagi.
 */
final class SendInvoiceNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    public function __construct(
        public Invoice $invoice,
    ) {}

    public function handle(): void
    {
        // Diisi di Tahap 07.
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gagal mengirim notifikasi tagihan terbit.', [
            'invoice_id' => $this->invoice->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
