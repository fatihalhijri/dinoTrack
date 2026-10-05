<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\BillingPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menerbitkan ulang periode yang invoice-nya dibatalkan. Nominal dihitung ulang dari subscription
 * saat ini. Jika pembatalannya karena salah input paket, admin bisa menyertakan paket koreksi:
 * paket dan harga subscription langsung dikoreksi (pengecualian K10, bukan ganti paket biasa).
 *
 * Boleh untuk pelanggan berstatus apa pun, termasuk `terminated`, karena periodenya sudah dipakai.
 */
final class ReissueInvoice
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Invoice $cancelled, ?int $correctedPackageId = null, ?User $by = null): Invoice
    {
        try {
            return DB::transaction(fn (): Invoice => $this->reissue($cancelled, $correctedPackageId, $by));
        } catch (UniqueConstraintViolationException) {
            // Dua penerbitan ulang bersamaan: unique (subscription_id, billed_period_start) menolak yang kedua.
            throw ValidationException::withMessages(['invoice' => 'Periode ini sudah punya tagihan aktif.']);
        }
    }

    private function reissue(Invoice $cancelled, ?int $correctedPackageId, ?User $by): Invoice
    {
        // Urutan lock mengikuti konvensi M11: pelanggan lebih dulu, lalu invoice.
        Customer::query()->lockForUpdate()->findOrFail($cancelled->customer_id);
        $cancelled = Invoice::query()->lockForUpdate()->findOrFail($cancelled->id);

        if ($cancelled->status !== InvoiceStatus::Cancelled) {
            throw ValidationException::withMessages(['invoice' => 'Hanya tagihan yang dibatalkan yang bisa diterbitkan ulang.']);
        }

        $hasActiveInvoice = Invoice::query()
            ->where('subscription_id', $cancelled->subscription_id)
            ->where('period_start', $cancelled->period_start->toDateString())
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->exists();

        if ($hasActiveInvoice) {
            throw ValidationException::withMessages(['invoice' => 'Periode ini sudah punya tagihan aktif.']);
        }

        $subscription = Subscription::query()->findOrFail($cancelled->subscription_id);

        if ($correctedPackageId !== null) {
            $this->correctPackage($subscription, $correctedPackageId, $cancelled, $by);
        }

        $invoice = $this->issueInvoice->handle(
            $subscription,
            new BillingPeriod($cancelled->period_start, $cancelled->period_end),
            today(),
            $by,
        );

        $this->logger->log('invoice.reissued', $invoice, $by, [
            'replaces_invoice_id' => $cancelled->id,
            'replaces_number' => $cancelled->number,
            'corrected_package_id' => $correctedPackageId,
        ]);

        return $invoice;
    }

    /**
     * Koreksi salah input: berlaku untuk periode yang diterbitkan ulang dan seterusnya.
     * Profil PPPoE di router menyusul di Tahap 06.
     */
    private function correctPackage(Subscription $subscription, int $packageId, Invoice $cancelled, ?User $by): void
    {
        $package = Package::query()->sharedLock()->findOrFail($packageId);

        if ($package->id === $subscription->package_id) {
            throw ValidationException::withMessages(['package_id' => 'Paket koreksi sama dengan paket yang sedang dipakai.']);
        }

        if (! $package->is_active) {
            throw ValidationException::withMessages(['package_id' => 'Paket sudah nonaktif dan tidak bisa dipilih.']);
        }

        $previous = ['package_id' => $subscription->package_id, 'price' => $subscription->price];

        $subscription->update([
            'package_id' => $package->id,
            'price' => $package->price,
            // Rencana ganti paket ke paket yang sama sudah tidak relevan setelah dikoreksi.
            'next_package_id' => $subscription->next_package_id === $package->id ? null : $subscription->next_package_id,
        ]);

        $this->logger->log('subscription.package_corrected', $subscription->customer, $by, [
            'subscription_id' => $subscription->id,
            'from_package_id' => $previous['package_id'],
            'from_price' => $previous['price'],
            'to_package_id' => $package->id,
            'to_price' => $package->price,
            'cancelled_invoice_id' => $cancelled->id,
        ]);
    }
}
