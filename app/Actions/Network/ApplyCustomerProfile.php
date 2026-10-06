<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Contracts\NetworkController;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Support\ActivityLogger;
use App\Support\CustomerNetworkLock;

/**
 * Memasang profil paket dan meng-enable secret pelanggan `active` tanpa mengubah statusnya:
 * aktivasi pelanggan baru, ganti paket yang berlaku, dan koreksi paket saat terbit ulang.
 *
 * Pelanggan `isolated` dilewati agar profil isolir tidak tertimpa (K10); profil barunya
 * dipasang saat isolir dibuka. Profil dibaca saat job berjalan, bukan saat dijadwalkan.
 */
final class ApplyCustomerProfile
{
    public function __construct(
        private readonly NetworkController $network,
        private readonly CustomerNetworkLock $lock,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * RouterUnreachableException dari router dibiarkan lolos agar job mencoba ulang.
     */
    public function handle(Customer $customer): bool
    {
        return $this->lock->run($customer, function () use ($customer): bool {
            $customer = Customer::query()->find($customer->id);

            if ($customer === null) {
                return false;
            }

            if ($customer->status !== CustomerStatus::Active) {
                $this->logger->log('customer.profile_skipped', $customer, null, [
                    'reason' => 'Profil paket hanya dipasang untuk pelanggan aktif.',
                    'status' => $customer->status->value,
                ]);

                return false;
            }

            $profile = $customer->activeSubscription()->firstOrFail()->package()->firstOrFail()->mikrotik_profile;

            $this->network->activate($customer, $profile);

            $customer->update(['network_error_at' => null, 'network_error' => null]);
            $this->logger->log('customer.profile_applied', $customer, null, ['profile' => $profile]);

            return true;
        });
    }
}
