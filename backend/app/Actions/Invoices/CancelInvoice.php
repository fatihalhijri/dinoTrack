<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hanya invoice `unpaid`/`overdue` yang bisa dibatalkan; `paid` dan `cancelled` adalah status akhir.
 */
final class CancelInvoice
{
    public const int MIN_REASON_LENGTH = 5;

    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Invoice $invoice, string $reason, ?User $by = null): Invoice
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
        }

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw ValidationException::withMessages([
                'reason' => sprintf('Alasan pembatalan minimal berisi %d karakter.', self::MIN_REASON_LENGTH),
            ]);
        }

        return DB::transaction(function () use ($invoice, $reason, $by): Invoice {
            // Dikunci agar tidak balapan dengan pembayaran yang masuk bersamaan.
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! in_array($invoice->status, InvoiceStatus::outstanding(), true)) {
                throw ValidationException::withMessages([
                    'status' => $invoice->status === InvoiceStatus::Paid
                        ? 'Tagihan yang sudah lunas tidak bisa dibatalkan.'
                        : 'Tagihan sudah dibatalkan.',
                ]);
            }

            $previousStatus = $invoice->status;

            $invoice->update([
                'status' => InvoiceStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
            ]);

            $this->logger->log('invoice.cancelled', $invoice, $by, [
                'number' => $invoice->number,
                'previous_status' => $previousStatus->value,
                'reason' => $reason,
            ]);

            return $invoice;
        });
    }
}
