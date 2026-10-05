<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Invoice `unpaid` yang jatuh temponya sudah lewat (due_at < hari ini) menjadi `overdue`.
 * Tidak ada denda di v1 (K5), sehingga total invoice tidak berubah.
 */
final class MarkOverdueInvoices
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @return int jumlah invoice yang berubah menjadi overdue
     */
    public function handle(CarbonImmutable $today): int
    {
        $marked = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Unpaid)
            ->where('due_at', '<', $today->toDateString())
            ->chunkById(200, function (Collection $invoices) use (&$marked): void {
                foreach ($invoices as $invoice) {
                    // Syarat status ikut di UPDATE agar tidak menimpa invoice yang baru saja lunas.
                    $updated = Invoice::query()
                        ->whereKey($invoice->id)
                        ->where('status', InvoiceStatus::Unpaid)
                        ->update(['status' => InvoiceStatus::Overdue]);

                    if ($updated === 0) {
                        continue;
                    }

                    $marked++;
                    $this->logger->log('invoice.overdue', $invoice, null, [
                        'number' => $invoice->number,
                        'due_at' => $invoice->due_at->toDateString(),
                    ]);
                }
            });

        return $marked;
    }
}
