<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Pembayaran tunai/transfer yang dicatat kasir. Tanggal bayar boleh mundur (uang diterima
 * kemarin baru dicatat hari ini), tetapi tidak di masa depan dan tidak sebelum tagihan terbit.
 */
final class RecordManualPayment
{
    public function __construct(
        private readonly MarkInvoicePaid $markInvoicePaid,
    ) {}

    public function handle(
        Invoice $invoice,
        PaymentMethod $method,
        int $amount,
        User $by,
        ?CarbonImmutable $paidAt = null,
        ?string $notes = null,
    ): Payment {
        if ($method === PaymentMethod::Qris) {
            throw ValidationException::withMessages([
                'method' => 'Pembayaran QRIS dicatat otomatis oleh payment gateway.',
            ]);
        }

        $paidAt ??= now();

        if ($paidAt->isFuture()) {
            throw ValidationException::withMessages(['paid_at' => 'Tanggal bayar tidak boleh di masa depan.']);
        }

        if ($paidAt->lessThan($invoice->issued_at->startOfDay())) {
            throw ValidationException::withMessages(['paid_at' => 'Tanggal bayar tidak boleh sebelum tanggal terbit tagihan.']);
        }

        $notes = $notes === null ? null : trim($notes);

        return $this->markInvoicePaid->handle(
            $invoice,
            $method,
            $amount,
            $paidAt,
            receivedBy: $by,
            notes: $notes === '' ? null : $notes,
        );
    }
}
