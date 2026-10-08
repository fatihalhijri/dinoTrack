<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Notifications\NotifyCustomer;
use App\Enums\InvoiceStatus;
use App\Enums\MessageTemplateKey;
use App\Enums\QueueName;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjadwalkan pesan WhatsApp `invoice_issued` untuk invoice yang baru terbit (dari IssueInvoice).
 * Invoice yang sudah lunas atau dibatalkan sebelum job berjalan tidak diberi tahu.
 */
#[Queue(QueueName::Notifications)]
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

    public function handle(NotifyCustomer $notify): void
    {
        if (! in_array($this->invoice->status, InvoiceStatus::outstanding(), true)) {
            return;
        }

        $notify->handle($this->invoice->customer, MessageTemplateKey::InvoiceIssued, $this->invoice);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gagal mengirim notifikasi tagihan terbit.', [
            'invoice_id' => $this->invoice->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
