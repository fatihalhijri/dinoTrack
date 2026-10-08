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
 * Pelanggan `pending` belum pernah ditagih, sehingga paketnya diganti langsung. Pelanggan
 * terpasang hanya dijadwalkan lewat `next_package_id` dan berlaku mulai periode berikutnya
 * tanpa prorata (docs/04 "Ganti paket").
 */
final class ChangeCustomerPackage
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  int|null  $packageId  null membatalkan rencana ganti paket.
     */
    public function handle(Customer $customer, ?int $packageId, ?User $by = null): Subscription
    {
        return DB::transaction(function () use ($customer, $packageId, $by): Subscription {
            // Status dibaca ulang di bawah lock agar tidak balapan dengan berhenti berlangganan.
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $subscription = $customer->activeSubscription;

            if ($customer->status === CustomerStatus::Terminated || $subscription === null) {
                throw ValidationException::withMessages(['package_id' => 'Pelanggan yang sudah berhenti tidak bisa ganti paket.']);
            }

            if ($packageId === null) {
                return $this->cancelScheduledChange($customer, $subscription, $by);
            }

            $package = Package::query()->sharedLock()->findOrFail($packageId);

            if ($package->id === $subscription->package_id) {
                throw ValidationException::withMessages(['package_id' => 'Paket baru sama dengan paket yang sedang dipakai.']);
            }

            if (! $package->is_active) {
                throw ValidationException::withMessages(['package_id' => 'Paket sudah nonaktif dan tidak bisa dipilih.']);
            }

            if ($customer->status === CustomerStatus::Pending) {
                $from = $subscription->package_id;
                $subscription->update(['package_id' => $package->id, 'price' => $package->price]);

                $this->logger->log('customer.package_changed', $customer, $by, [
                    'from_package_id' => $from,
                    'to_package_id' => $package->id,
                    'price' => $package->price,
                ]);

                return $subscription;
            }

            $subscription->update(['next_package_id' => $package->id]);

            $this->logger->log('customer.package_change_scheduled', $customer, $by, [
                'from_package_id' => $subscription->package_id,
                'to_package_id' => $package->id,
            ]);

            return $subscription;
        });
    }

    private function cancelScheduledChange(Customer $customer, Subscription $subscription, ?User $by): Subscription
    {
        if ($subscription->next_package_id === null) {
            throw ValidationException::withMessages(['package_id' => 'Tidak ada rencana ganti paket yang bisa dibatalkan.']);
        }

        $cancelled = $subscription->next_package_id;
        $subscription->update(['next_package_id' => null]);

        $this->logger->log('customer.package_change_cancelled', $customer, $by, [
            'cancelled_package_id' => $cancelled,
        ]);

        return $subscription;
    }
}
