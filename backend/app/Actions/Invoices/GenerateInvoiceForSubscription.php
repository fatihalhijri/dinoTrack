<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\BillingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Menerbitkan invoice satu periode untuk satu subscription. Mengembalikan null jika periode
 * itu tidak perlu ditagih (sudah punya invoice, pelanggan tidak aktif, subscription berakhir).
 *
 * Periode yang invoice-nya dibatalkan juga dianggap sudah ditagih: penagihan ulangnya selalu
 * keputusan eksplisit admin lewat ReissueInvoice.
 */
final class GenerateInvoiceForSubscription
{
    public function __construct(
        private readonly IssueInvoice $issueInvoice,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  User|null  $by  pelaku jika dipicu pengguna (misalnya aktivasi); null untuk scheduler.
     */
    public function handle(Subscription $subscription, BillingPeriod $period, CarbonImmutable $issuedAt, ?User $by = null): ?Invoice
    {
        try {
            return DB::transaction(fn (): ?Invoice => $this->generate($subscription, $period, $issuedAt, $by));
        } catch (UniqueConstraintViolationException $exception) {
            // Pengaman terakhir jika dua proses lolos pengecekan bersamaan. Hanya bentrok periode
            // yang berarti "sudah ditagih"; bentrok lain (misalnya nomor invoice) adalah galat nyata.
            if ($this->isBilled($subscription, $period)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isBilled(Subscription $subscription, BillingPeriod $period): bool
    {
        return Invoice::query()
            ->whereBelongsTo($subscription)
            ->where('period_start', $period->start->toDateString())
            ->exists();
    }

    private function generate(Subscription $subscription, BillingPeriod $period, CarbonImmutable $issuedAt, ?User $by): ?Invoice
    {
        // Baris pelanggan dikunci lebih dulu (konvensi M11) agar generator yang berjalan
        // bersamaan, aktivasi, dan berhenti berlangganan saling menunggu.
        $customer = Customer::query()->lockForUpdate()->find($subscription->customer_id);
        $subscription->refresh();

        if (
            $customer === null
            || ! in_array($customer->status, [CustomerStatus::Active, CustomerStatus::Isolated], true)
            || $subscription->ends_at !== null
            || $subscription->starts_at === null
        ) {
            return null;
        }

        if ($this->isBilled($subscription, $period)) {
            return null;
        }

        $isFirstPeriod = $period->start->equalTo($subscription->starts_at);

        if (! $isFirstPeriod && $subscription->next_package_id !== null) {
            $this->applyScheduledPackageChange($subscription, $customer);
        }

        return $this->issueInvoice->handle($subscription, $period, $issuedAt, $by);
    }

    /**
     * Ganti paket berlaku mulai periode berikutnya, tanpa prorata (K10). Profil PPPoE di router
     * belum diubah di sini; itu bagian Tahap 06.
     */
    private function applyScheduledPackageChange(Subscription $subscription, Customer $customer): void
    {
        $package = Package::query()->findOrFail($subscription->next_package_id);
        $previousPackageId = $subscription->package_id;

        $subscription->update([
            'package_id' => $package->id,
            'price' => $package->price,
            'next_package_id' => null,
        ]);

        $this->logger->log('subscription.package_applied', $customer, null, [
            'subscription_id' => $subscription->id,
            'from_package_id' => $previousPackageId,
            'to_package_id' => $package->id,
            'price' => $package->price,
        ]);
    }
}
