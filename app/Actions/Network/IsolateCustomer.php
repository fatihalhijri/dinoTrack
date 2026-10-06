<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Contracts\NetworkController;
use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Jobs\ActivateCustomerJob;
use App\Jobs\SendIsolationNotificationJob;
use App\Models\Customer;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\CustomerNetworkLock;
use App\Support\IsolationRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Mengisolir pelanggan: router lebih dulu, status database hanya berubah jika router berhasil.
 * Dipanggil dari IsolateCustomerJob, sehingga syaratnya dicek ulang saat job berjalan
 * (pelanggan bisa saja sudah membayar sejak job dijadwalkan).
 *
 * Mengembalikan false jika isolir dilewati karena syaratnya tidak lagi terpenuhi.
 */
final class IsolateCustomer
{
    public function __construct(
        private readonly NetworkController $network,
        private readonly IsolationRules $rules,
        private readonly CustomerNetworkLock $lock,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * RouterUnreachableException dari router dibiarkan lolos: status tidak berubah dan job mencoba ulang.
     *
     * @param  CarbonImmutable  $today  tanggal penilaian tunggakan (isolir otomatis)
     * @param  string|null  $note  alasan dari admin (isolir manual)
     */
    public function handle(Customer $customer, IsolationReason $reason, CarbonImmutable $today, ?User $by = null, ?string $note = null): bool
    {
        return $this->lock->run($customer, function () use ($customer, $reason, $today, $by, $note): bool {
            $customer = Customer::query()->find($customer->id);

            if ($customer === null) {
                return false;
            }

            $skipReason = $this->skipReason($customer, $reason, $today);

            if ($skipReason !== null) {
                $this->logger->log('customer.isolation_skipped', $customer, $by, ['reason' => $skipReason, 'isolation_reason' => $reason->value]);

                return false;
            }

            // Isolir manual atas pelanggan yang sudah diisolir otomatis: profil isolir sudah
            // terpasang di router, yang berubah hanya alasannya agar pembayaran tidak membukanya.
            $isAlreadyIsolated = $customer->status === CustomerStatus::Isolated;

            if (! $isAlreadyIsolated) {
                $this->network->isolate($customer);
            }

            return DB::transaction(fn (): bool => $this->markIsolated($customer, $reason, $today, $isAlreadyIsolated, $by, $note));
        });
    }

    private function markIsolated(Customer $customer, IsolationReason $reason, CarbonImmutable $today, bool $isAlreadyIsolated, ?User $by, ?string $note): bool
    {
        $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
        $previousReason = $customer->isolation_reason;

        // Status bisa berubah selama router dipanggil, misalnya pelanggan diberhentikan.
        if (! in_array($customer->status, [CustomerStatus::Active, CustomerStatus::Isolated], true)) {
            $this->logger->log('customer.isolation_skipped', $customer, $by, [
                'reason' => 'Status pelanggan berubah saat router dipanggil.',
                'isolation_reason' => $reason->value,
            ]);

            return false;
        }

        $customer->update([
            'status' => CustomerStatus::Isolated,
            'isolated_at' => $isAlreadyIsolated ? $customer->isolated_at : now(),
            'isolation_reason' => $reason,
            'network_error_at' => null,
            'network_error' => null,
        ]);

        $this->logger->log('customer.isolated', $customer, $by, [
            'isolation_reason' => $reason->value,
            'previous_isolation_reason' => $previousReason?->value,
            'note' => $note,
        ]);

        if (! $isAlreadyIsolated) {
            SendIsolationNotificationJob::dispatch($customer)->afterCommit();
        }

        // Pembayaran bisa masuk selama router dipanggil. MarkInvoicePaid melihat pelanggan masih
        // `active` sehingga tidak memicu aktivasi; di sini status sudah sesuai router, jadi
        // aktivasi dijadwalkan agar pelanggan yang sudah lunas tidak tetap terisolir.
        if ($this->rules->shouldAutoActivate($customer, $today)) {
            ActivateCustomerJob::dispatch($customer)->afterCommit();
        }

        return true;
    }

    private function skipReason(Customer $customer, IsolationReason $reason, CarbonImmutable $today): ?string
    {
        if (! in_array($customer->status, [CustomerStatus::Active, CustomerStatus::Isolated], true)) {
            return 'Pelanggan tidak berstatus aktif.';
        }

        if ($reason === IsolationReason::Manual) {
            return $customer->isolation_reason === IsolationReason::Manual ? 'Pelanggan sudah diisolir manual.' : null;
        }

        if ($customer->status === CustomerStatus::Isolated) {
            return 'Pelanggan sudah diisolir.';
        }

        if (! $this->rules->shouldAutoIsolate($customer, $today)) {
            return 'Isolir otomatis dimatikan atau tidak ada tunggakan lewat masa toleransi.';
        }

        return null;
    }
}
