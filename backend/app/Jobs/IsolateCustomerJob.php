<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Network\IsolateCustomer;
use App\Concerns\HandlesRouterFailures;
use App\Enums\IsolationReason;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Mengisolir pelanggan di router lalu mengubah status. Unik per pelanggan dan alasan isolir,
 * agar permintaan isolir manual admin tidak terbuang karena isolir otomatis yang sedang antre.
 */
final class IsolateCustomerJob implements ShouldBeUnique, ShouldQueue
{
    use HandlesRouterFailures, Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    /** Menunggu lock pelanggan (maks. 30 detik) ditambah satu operasi router. */
    public int $timeout = 100;

    /** Lock unik dilepas paksa setelah 1 jam agar job yang hilang dari queue tidak memblokir selamanya. */
    public int $uniqueFor = 3600;

    /**
     * @param  CarbonImmutable  $today  tanggal penilaian tunggakan saat dijadwalkan
     */
    public function __construct(
        public Customer $customer,
        public IsolationReason $reason,
        public CarbonImmutable $today,
        public ?User $by = null,
        public ?string $note = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->customer->id.':'.$this->reason->value;
    }

    public function handle(IsolateCustomer $isolate): void
    {
        $this->runRouterCommand(fn (): bool => $isolate->handle($this->customer, $this->reason, $this->today, $this->by, $this->note));
    }

    public function failed(?Throwable $exception): void
    {
        $this->flagRouterFailure('customer.isolation_failed', 'Gagal mengisolir pelanggan di router.', $exception);
    }
}
