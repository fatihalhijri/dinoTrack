<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Data\GatewayNotification;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentChargeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentCharge;
use App\Support\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menerapkan status transaksi dari gateway (webhook atau rekonsiliasi) ke charge, invoice,
 * dan pembayaran. Idempotent: satu charge paling banyak menghasilkan satu pembayaran,
 * sehingga notifikasi ganda atau bersamaan tidak membuat pembayaran ganda.
 *
 * Pembayaran yang tidak bisa diterapkan normal (invoice sudah lunas/dibatalkan, nominal tidak
 * cocok) tetap dicatat sebagai `needs_review` tanpa mengubah invoice (K6). Charge yang sudah
 * `expired`/`failed` di database tetapi tetap dibayar diterapkan normal selama invoice masih
 * terbuka dan nominalnya cocok.
 */
final class ProcessGatewayNotification
{
    public function __construct(
        private readonly MarkInvoicePaid $markInvoicePaid,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(GatewayNotification $notification): void
    {
        $charge = PaymentCharge::query()->where('order_id', $notification->orderId)->first();

        if ($charge === null) {
            // Misalnya notifikasi uji dari dashboard Midtrans atau transaksi dari sistem lain.
            Log::warning('Notifikasi pembayaran untuk order_id yang tidak dikenal diabaikan.', [
                'order_id' => $notification->orderId,
                'transaction_status' => $notification->transactionStatus,
            ]);

            return;
        }

        if ($notification->status === null) {
            $this->recordUnhandledStatus($charge, $notification);

            return;
        }

        // Dibaca di luar transaksi: pembacaan biasa di dalam transaksi MySQL (REPEATABLE READ)
        // membuat snapshot sebelum lock didapat, sehingga perubahan proses lain tidak terlihat.
        $customerId = Invoice::query()->whereKey($charge->invoice_id)->value('customer_id');

        DB::transaction(function () use ($charge, $customerId, $notification): void {
            // Urutan lock mengikuti konvensi M11: pelanggan, invoice, lalu charge.
            Customer::query()->lockForUpdate()->findOrFail($customerId);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($charge->invoice_id);
            $charge = PaymentCharge::query()->lockForUpdate()->findOrFail($charge->id);

            match ($notification->status) {
                PaymentChargeStatus::Settled => $this->settle($invoice, $charge, $notification),
                PaymentChargeStatus::Expired, PaymentChargeStatus::Failed => $this->close($charge, $notification->status),
                default => null,
            };
        });
    }

    private function settle(Invoice $invoice, PaymentCharge $charge, GatewayNotification $notification): void
    {
        // Locking read agar pembayaran yang baru di-commit proses lain ikut terlihat.
        if ($charge->payments()->lockForUpdate()->exists()) {
            return;
        }

        $charge->update(['status' => PaymentChargeStatus::Settled]);

        $paidAt = $notification->paidAt ?? now();
        $anomaly = $this->anomalyReason($invoice, $charge, $notification);

        if ($anomaly === null) {
            $this->markInvoicePaid->handle(
                $invoice,
                PaymentMethod::Qris,
                $notification->grossAmount,
                $paidAt,
                charge: $charge,
                reference: $notification->reference,
            );

            return;
        }

        $payment = $invoice->payments()->create([
            'payment_charge_id' => $charge->id,
            'method' => PaymentMethod::Qris,
            'amount' => $notification->grossAmount,
            'paid_at' => $paidAt,
            'reference' => $notification->reference,
            'review_status' => PaymentReviewStatus::NeedsReview,
            'review_note' => $anomaly,
        ]);

        $this->logger->log('payment.needs_review', $payment, null, [
            'invoice_number' => $invoice->number,
            'order_id' => $charge->order_id,
            'amount' => $notification->grossAmount,
            'reason' => $anomaly,
        ]);

        Log::warning('Pembayaran QRIS anomali perlu ditinjau admin.', [
            'payment_id' => $payment->id,
            'order_id' => $charge->order_id,
            'reason' => $anomaly,
        ]);
    }

    /**
     * Status akhir tidak ditimpa: notifikasi `expire` yang datang setelah `settlement` diabaikan.
     */
    private function close(PaymentCharge $charge, PaymentChargeStatus $status): void
    {
        if ($charge->status === PaymentChargeStatus::Pending) {
            $charge->update(['status' => $status]);
        }
    }

    private function anomalyReason(Invoice $invoice, PaymentCharge $charge, GatewayNotification $notification): ?string
    {
        return match (true) {
            $invoice->status === InvoiceStatus::Paid => 'Tagihan sudah lunas sebelum pembayaran QRIS ini masuk.',
            $invoice->status === InvoiceStatus::Cancelled => 'Tagihan sudah dibatalkan sebelum pembayaran QRIS ini masuk.',
            $notification->grossAmount !== $charge->amount => sprintf(
                'Nominal dibayar %s tidak sama dengan nominal charge %s.',
                Money::format($notification->grossAmount),
                Money::format($charge->amount),
            ),
            $charge->amount !== $invoice->total => sprintf(
                'Nominal charge %s tidak sama dengan total tagihan %s.',
                Money::format($charge->amount),
                Money::format($invoice->total),
            ),
            default => null,
        };
    }

    /**
     * Refund dan chargeback dilakukan di luar sistem pada v1, sehingga data tidak diubah;
     * admin cukup diberi jejak untuk dicocokkan.
     */
    private function recordUnhandledStatus(PaymentCharge $charge, GatewayNotification $notification): void
    {
        $context = [
            'order_id' => $charge->order_id,
            'transaction_status' => $notification->transactionStatus,
            'gross_amount' => $notification->grossAmount,
        ];

        if ($notification->isReversal()) {
            $this->logger->log('payment.gateway_reversal', $charge, null, $context);
            Log::warning('Gateway melaporkan refund/chargeback; data pembayaran tidak diubah.', $context);

            return;
        }

        Log::warning('Status transaksi gateway tidak dikenal diabaikan.', $context);
    }
}
