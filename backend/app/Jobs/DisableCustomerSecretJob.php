<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Concerns\HandlesRouterFailures;
use App\Contracts\NetworkController;
use App\Enums\CustomerStatus;
use App\Enums\QueueName;
use App\Models\Customer;
use App\Support\ActivityLogger;
use App\Support\CustomerNetworkLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Throwable;

/**
 * Menonaktifkan (bukan menghapus) secret PPPoE pelanggan yang berhenti berlangganan.
 */
#[Queue(QueueName::Network)]
final class DisableCustomerSecretJob implements ShouldBeUnique, ShouldQueue
{
    use HandlesRouterFailures, Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    /** Menunggu lock pelanggan (maks. 30 detik) ditambah satu operasi router. */
    public int $timeout = 100;

    /** Lock unik dilepas paksa setelah 1 jam agar job yang hilang dari queue tidak memblokir selamanya. */
    public int $uniqueFor = 3600;

    public function __construct(
        public Customer $customer,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->customer->id;
    }

    /**
     * Status dicek ulang saat job berjalan: selama job dicoba ulang, pelanggan bisa saja sudah
     * diaktifkan kembali, dan secret-nya tidak boleh dinonaktifkan lagi. Dijalankan di bawah
     * lock pelanggan yang sama dengan isolir dan aktivasi.
     *
     * RouterUnreachableException dibiarkan lolos agar job dicoba ulang. Secret yang tidak
     * ditemukan tidak dicoba ulang: kemungkinan username salah dan secret aslinya masih aktif,
     * sehingga admin perlu memeriksanya.
     */
    public function handle(NetworkController $network, ActivityLogger $logger, CustomerNetworkLock $lock): void
    {
        $lock->run($this->customer, function () use ($network, $logger): void {
            if ($this->customer->fresh()?->status !== CustomerStatus::Terminated) {
                $logger->log('customer.secret_disable_skipped', $this->customer, null, [
                    'reason' => 'Pelanggan tidak lagi berstatus berhenti.',
                ]);

                return;
            }

            $this->runRouterCommand(function () use ($network, $logger): void {
                $network->disableSecret($this->customer);

                Customer::query()->whereKey($this->customer->id)->update(['network_error_at' => null, 'network_error' => null]);
                $logger->log('customer.secret_disabled', $this->customer);
            });
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->flagRouterFailure('customer.secret_disable_failed', 'Gagal menonaktifkan secret PPPoE pelanggan.', $exception);
    }
}
