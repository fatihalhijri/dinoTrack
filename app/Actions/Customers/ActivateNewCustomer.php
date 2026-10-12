<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Actions\Invoices\GenerateInvoiceForSubscription;
use App\Enums\CustomerStatus;
use App\Jobs\ApplyCustomerProfileJob;
use App\Models\Customer;
use App\Models\Router;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\BillingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menandai pelanggan `pending` sebagai terpasang (K3): dalam satu transaksi status menjadi
 * `active`, tanggal mulai langganan diisi, dan tagihan pertama langsung terbit. Jika tanggal
 * pasang mundur melewati `billing_day`, periode berikutnya ikut ditagih.
 *
 * Secret PPPoE di-enable dengan profil paket lewat ApplyCustomerProfileJob setelah commit.
 */
final class ActivateNewCustomer
{
    public function __construct(
        private readonly GenerateInvoiceForSubscription $generateInvoice,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Customer $customer, CarbonImmutable $installedAt, ?User $by = null): Customer
    {
        $today = today();
        $installedAt = $installedAt->startOfDay();

        return DB::transaction(function () use ($customer, $installedAt, $today, $by): Customer {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($customer->status !== CustomerStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'Hanya pelanggan yang belum terpasang yang bisa ditandai terpasang.']);
            }

            $subscription = $customer->activeSubscription;

            if ($subscription === null) {
                throw ValidationException::withMessages(['status' => 'Pelanggan belum punya langganan aktif.']);
            }

            $this->ensureValidInstallDate($customer, $subscription, $installedAt, $today);

            if (! Router::query()->whereKey($customer->router_id)->sharedLock()->value('is_active')) {
                throw ValidationException::withMessages(['router_id' => 'Router pelanggan nonaktif. Ubah router pelanggan terlebih dahulu.']);
            }

            $customer->update(['status' => CustomerStatus::Active, 'installed_at' => $installedAt->toDateString()]);
            $subscription->update(['starts_at' => $installedAt->toDateString()]);

            $this->logger->log('customer.activated', $customer, $by, [
                'installed_at' => $installedAt->toDateString(),
                'subscription_id' => $subscription->id,
            ]);

            // Router lewat job yang dicoba ulang: aktivasi tidak gagal hanya karena router sedang mati.
            ApplyCustomerProfileJob::dispatch($customer)->afterCommit();

            $periods = BillingPeriod::due($installedAt, $subscription->billing_day, null, $today, fromFirstPeriod: true);

            foreach ($periods as $period) {
                $this->generateInvoice->handle($subscription, $period, $today, $by);
            }

            return $customer;
        });
    }

    /**
     * Batas bawah tanggal pasang adalah tanggal pendaftaran langganan ini. Pelanggan yang
     * didaftarkan kembali setelah berhenti memakai tanggal pendaftaran ulang, bukan tanggal
     * pendaftaran pertama, agar periode yang sudah ditagih langganan lama tidak ditagih lagi.
     */
    private function ensureValidInstallDate(Customer $customer, Subscription $subscription, CarbonImmutable $installedAt, CarbonImmutable $today): void
    {
        if ($installedAt->greaterThan($today)) {
            throw ValidationException::withMessages(['installed_at' => 'Tanggal pasang tidak boleh di masa depan.']);
        }

        $registeredAt = $subscription->created_at === null || $customer->created_at?->greaterThan($subscription->created_at)
            ? $customer->created_at
            : $subscription->created_at;

        if ($registeredAt !== null && $installedAt->lessThan($registeredAt->startOfDay())) {
            throw ValidationException::withMessages(['installed_at' => 'Tanggal pasang tidak boleh sebelum tanggal pendaftaran pelanggan.']);
        }
    }
}
