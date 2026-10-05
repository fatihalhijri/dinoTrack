<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\NetworkController;
use App\Enums\CustomerStatus;
use App\Exceptions\SecretNotFoundException;
use App\Models\Customer;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menonaktifkan (bukan menghapus) secret PPPoE pelanggan yang berhenti berlangganan.
 */
final class DisableCustomerSecretJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public int $timeout = 30;

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
     * diaktifkan kembali, dan secret-nya tidak boleh dinonaktifkan lagi.
     *
     * RouterUnreachableException dibiarkan lolos agar job dicoba ulang. Secret yang tidak
     * ditemukan tidak dicoba ulang: kemungkinan username salah dan secret aslinya masih aktif,
     * sehingga admin perlu memeriksanya.
     */
    public function handle(NetworkController $network, ActivityLogger $logger): void
    {
        if ($this->customer->fresh()?->status !== CustomerStatus::Terminated) {
            $logger->log('customer.secret_disable_skipped', $this->customer, null, [
                'reason' => 'Pelanggan tidak lagi berstatus berhenti.',
            ]);

            return;
        }

        try {
            $network->disableSecret($this->customer);
        } catch (SecretNotFoundException $exception) {
            $this->fail($exception);

            return;
        }

        $logger->log('customer.secret_disabled', $this->customer);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gagal menonaktifkan secret PPPoE pelanggan.', [
            'customer_id' => $this->customer->id,
            'pppoe_username' => $this->customer->pppoe_username,
            'error' => $exception?->getMessage(),
        ]);

        app(ActivityLogger::class)->log('customer.secret_disable_failed', $this->customer, null, [
            'error' => $exception?->getMessage(),
        ]);
    }
}
