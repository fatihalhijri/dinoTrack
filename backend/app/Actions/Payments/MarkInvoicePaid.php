<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\IsolationReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Jobs\ActivateCustomerJob;
use App\Jobs\SendPaymentConfirmationJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentCharge;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Money;
use App\Support\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Melunasi invoice dengan satu pembayaran normal (K4: satu pembayaran = satu invoice, nominal
 * pas). Dipakai bersama oleh pembayaran manual dan QRIS; pembayaran anomali tidak lewat sini.
 *
 * Pelanggan isolir-otomatis yang tidak lagi punya tunggakan lewat toleransi diaktifkan
 * kembali lewat ActivateCustomerJob. Isolir manual hanya dibuka admin (K8).
 */
final class MarkInvoicePaid
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(
        Invoice $invoice,
        PaymentMethod $method,
        int $amount,
        CarbonImmutable $paidAt,
        ?User $receivedBy = null,
        ?string $notes = null,
        ?PaymentCharge $charge = null,
        ?string $reference = null,
    ): Payment {
        return DB::transaction(function () use ($invoice, $method, $amount, $paidAt, $receivedBy, $notes, $charge, $reference): Payment {
            // Urutan lock mengikuti konvensi M11: pelanggan lebih dulu, lalu invoice.
            $customer = Customer::query()->lockForUpdate()->findOrFail($invoice->customer_id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            $this->ensurePayable($invoice, $amount);

            $payment = $invoice->payments()->create([
                'payment_charge_id' => $charge?->id,
                'method' => $method,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'reference' => $reference,
                'received_by' => $receivedBy?->id,
                'notes' => $notes,
                'review_status' => PaymentReviewStatus::None,
            ]);

            $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => $paidAt]);

            $shouldActivate = $this->shouldActivate($customer);

            $this->logger->log('payment.received', $payment, $receivedBy, [
                'invoice_number' => $invoice->number,
                'method' => $method->value,
                'amount' => $amount,
                'activation_queued' => $shouldActivate,
            ]);

            SendPaymentConfirmationJob::dispatch($payment)->afterCommit();

            if ($shouldActivate) {
                ActivateCustomerJob::dispatch($customer)->afterCommit();
            }

            return $payment;
        });
    }

    private function ensurePayable(Invoice $invoice, int $amount): void
    {
        if (! in_array($invoice->status, InvoiceStatus::outstanding(), true)) {
            throw ValidationException::withMessages([
                'invoice' => $invoice->status === InvoiceStatus::Paid
                    ? 'Tagihan sudah lunas.'
                    : 'Tagihan sudah dibatalkan.',
            ]);
        }

        if ($amount !== $invoice->total) {
            throw ValidationException::withMessages([
                'amount' => sprintf('Nominal pembayaran harus sama dengan total tagihan %s.', Money::format($invoice->total)),
            ]);
        }
    }

    /**
     * Dipanggil setelah invoice ini ditandai lunas, sehingga yang dihitung hanya tunggakan lain.
     */
    private function shouldActivate(Customer $customer): bool
    {
        return $customer->status === CustomerStatus::Isolated
            && $customer->isolation_reason === IsolationReason::Overdue
            && $this->settings->autoActivate()
            && ! $customer->invoices()->pastGracePeriod(today(), $this->settings->graceDays())->exists();
    }
}
