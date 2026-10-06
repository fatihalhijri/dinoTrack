<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Network\ApplyCustomerProfile;
use App\Concerns\HandlesRouterFailures;
use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Memasang profil paket dan meng-enable secret pelanggan `active` setelah aktivasi pelanggan
 * baru, ganti paket yang berlaku, atau koreksi paket. Profil dibaca saat job berjalan, jadi
 * satu job yang antre cukup untuk beberapa perubahan sekaligus.
 */
final class ApplyCustomerProfileJob implements ShouldBeUnique, ShouldQueue
{
    use HandlesRouterFailures, Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

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

    public function handle(ApplyCustomerProfile $applyProfile): void
    {
        $this->runRouterCommand(fn (): bool => $applyProfile->handle($this->customer));
    }

    public function failed(?Throwable $exception): void
    {
        $this->flagRouterFailure('customer.profile_failed', 'Gagal memasang profil paket pelanggan di router.', $exception);
    }
}
