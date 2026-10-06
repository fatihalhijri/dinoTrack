<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Notifications\NotifyCustomer;
use App\Enums\MessageTemplateKey;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjadwalkan pesan WhatsApp `payment_received` untuk pembayaran normal (bukan anomali),
 * di-dispatch dari MarkInvoicePaid.
 */
final class SendPaymentConfirmationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    public function __construct(
        public Payment $payment,
    ) {}

    public function handle(NotifyCustomer $notify): void
    {
        $invoice = $this->payment->invoice;

        $notify->handle($invoice->customer, MessageTemplateKey::PaymentReceived, $invoice);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gagal mengirim konfirmasi pembayaran.', [
            'payment_id' => $this->payment->id,
            'invoice_id' => $this->payment->invoice_id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
