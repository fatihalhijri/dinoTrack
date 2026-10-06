<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Network\ActivateCustomer;
use App\Concerns\HandlesRouterFailures;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Membuka isolir pelanggan di router (profil paket + kick sesi) lalu mengubah status menjadi
 * `active`. Otomatis dari MarkInvoicePaid, CancelInvoice, dan IsolateCustomer; manual dari
 * ActivateCustomerManually. Unik per pelanggan dan mode, agar permintaan admin tidak terbuang
 * karena aktivasi otomatis yang sedang antre (yang mungkin akan dilewati).
 */
final class ActivateCustomerJob implements ShouldBeUnique, ShouldQueue
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
        public bool $isManual = false,
        public ?User $by = null,
        public ?string $note = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->customer->id.':'.($this->isManual ? 'manual' : 'auto');
    }

    public function handle(ActivateCustomer $activate): void
    {
        $this->runRouterCommand(fn (): bool => $activate->handle($this->customer, $this->isManual, $this->by, $this->note));
    }

    public function failed(?Throwable $exception): void
    {
        $this->flagRouterFailure('customer.activation_failed', 'Gagal mengaktifkan pelanggan di router.', $exception);
    }
}
