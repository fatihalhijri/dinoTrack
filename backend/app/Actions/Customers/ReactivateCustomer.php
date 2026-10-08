<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pelanggan `terminated` berlangganan lagi: kembali `pending` dengan subscription baru,
 * kode dan riwayat tetap. Selanjutnya mengikuti alur aktivasi pelanggan baru.
 */
final class ReactivateCustomer
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Customer $customer, int $packageId, int $billingDay, ?User $by = null): Customer
    {
        return DB::transaction(function () use ($customer, $packageId, $billingDay, $by): Customer {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($customer->status !== CustomerStatus::Terminated) {
                throw ValidationException::withMessages(['status' => 'Hanya pelanggan yang sudah berhenti yang bisa diaktifkan kembali.']);
            }

            $package = Package::query()->sharedLock()->findOrFail($packageId);

            if (! $package->is_active) {
                throw ValidationException::withMessages(['package_id' => 'Paket sudah nonaktif dan tidak bisa dipilih.']);
            }

            $previousInstalledAt = $customer->installed_at?->toDateString();

            $customer->update([
                'status' => CustomerStatus::Pending,
                'installed_at' => null,
                'terminated_at' => null,
            ]);

            $subscription = $customer->subscriptions()->create([
                'package_id' => $package->id,
                'price' => $package->price,
                'billing_day' => min($billingDay, Subscription::MAX_BILLING_DAY),
                'starts_at' => null,
            ]);

            $this->logger->log('customer.reactivated', $customer, $by, [
                'package_id' => $package->id,
                'price' => $subscription->price,
                'billing_day' => $subscription->billing_day,
                'previous_installed_at' => $previousInstalledAt,
            ]);

            return $customer;
        });
    }
}
