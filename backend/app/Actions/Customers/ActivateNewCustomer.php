<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Actions\Invoices\GenerateInvoiceForSubscription;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Router;
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
 * Pengaktifan secret PPPoE di router menyusul di Tahap 06.
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

            $this->ensureValidInstallDate($customer, $installedAt, $today);

            if (! Router::query()->whereKey($customer->router_id)->sharedLock()->value('is_active')) {
                throw ValidationException::withMessages(['router_id' => 'Router pelanggan nonaktif. Ubah router pelanggan terlebih dahulu.']);
            }

            $subscription = $customer->activeSubscription;

            if ($subscription === null) {
                throw ValidationException::withMessages(['status' => 'Pelanggan belum punya langganan aktif.']);
            }

            $customer->update(['status' => CustomerStatus::Active, 'installed_at' => $installedAt->toDateString()]);
            $subscription->update(['starts_at' => $installedAt->toDateString()]);

            $this->logger->log('customer.activated', $customer, $by, [
                'installed_at' => $installedAt->toDateString(),
                'subscription_id' => $subscription->id,
            ]);

            $periods = BillingPeriod::due($installedAt, $subscription->billing_day, null, $today, fromFirstPeriod: true);

            foreach ($periods as $period) {
                $this->generateInvoice->handle($subscription, $period, $today, $by);
            }

            return $customer;
        });
    }

    private function ensureValidInstallDate(Customer $customer, CarbonImmutable $installedAt, CarbonImmutable $today): void
    {
        if ($installedAt->greaterThan($today)) {
            throw ValidationException::withMessages(['installed_at' => 'Tanggal pasang tidak boleh di masa depan.']);
        }

        $registeredAt = $customer->created_at?->startOfDay();

        if ($registeredAt !== null && $installedAt->lessThan($registeredAt)) {
            throw ValidationException::withMessages(['installed_at' => 'Tanggal pasang tidak boleh sebelum tanggal pendaftaran pelanggan.']);
        }
    }
}
