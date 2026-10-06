<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Contracts\NetworkController;
use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Models\Customer;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\CustomerNetworkLock;
use App\Support\IsolationRules;
use Illuminate\Support\Facades\DB;

/**
 * Membuka isolir: profil paket dipasang kembali di router, lalu status menjadi `active`.
 * Profil diambil dari subscription saat ini, sehingga ganti paket yang berlaku selama
 * pelanggan diisolir (K10) ikut terpasang di sini.
 *
 * Aktivasi otomatis (setelah lunas atau invoice dibatalkan) dicek ulang saat job berjalan dan
 * tidak pernah membuka isolir manual. Aktivasi manual oleh admin membuka isolir apa pun.
 * Mengembalikan false jika aktivasi dilewati.
 */
final class ActivateCustomer
{
    public function __construct(
        private readonly NetworkController $network,
        private readonly IsolationRules $rules,
        private readonly CustomerNetworkLock $lock,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * RouterUnreachableException dari router dibiarkan lolos: status tidak berubah dan job mencoba ulang.
     */
    public function handle(Customer $customer, bool $isManual = false, ?User $by = null, ?string $note = null): bool
    {
        return $this->lock->run($customer, function () use ($customer, $isManual, $by, $note): bool {
            $customer = Customer::query()->find($customer->id);

            if ($customer === null) {
                return false;
            }

            $skipReason = $this->skipReason($customer, $isManual);

            if ($skipReason !== null) {
                $this->logger->log('customer.activation_skipped', $customer, $by, ['reason' => $skipReason, 'manual' => $isManual]);

                return false;
            }

            $profile = $customer->activeSubscription()->firstOrFail()->package()->firstOrFail()->mikrotik_profile;

            $this->network->activate($customer, $profile);

            return DB::transaction(fn (): bool => $this->markActive($customer, $profile, $isManual, $by, $note));
        });
    }

    private function markActive(Customer $customer, string $profile, bool $isManual, ?User $by, ?string $note): bool
    {
        $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

        if ($customer->status !== CustomerStatus::Isolated) {
            $this->logger->log('customer.activation_skipped', $customer, $by, [
                'reason' => 'Status pelanggan berubah saat router dipanggil.',
                'manual' => $isManual,
            ]);

            return false;
        }

        $previousReason = $customer->isolation_reason;

        $customer->update([
            'status' => CustomerStatus::Active,
            'isolated_at' => null,
            'isolation_reason' => null,
            'network_error_at' => null,
            'network_error' => null,
        ]);

        $this->logger->log('customer.isolation_lifted', $customer, $by, [
            'manual' => $isManual,
            'previous_isolation_reason' => $previousReason?->value,
            'profile' => $profile,
            'note' => $note,
        ]);

        return true;
    }

    private function skipReason(Customer $customer, bool $isManual): ?string
    {
        if ($customer->status !== CustomerStatus::Isolated) {
            return 'Pelanggan tidak sedang diisolir.';
        }

        if ($isManual) {
            return null;
        }

        if ($customer->isolation_reason !== IsolationReason::Overdue) {
            return 'Isolir manual hanya bisa dibuka admin.';
        }

        if (! $this->rules->shouldAutoActivate($customer, today())) {
            return 'Aktivasi otomatis dimatikan atau masih ada tunggakan lewat masa toleransi.';
        }

        return null;
    }
}
