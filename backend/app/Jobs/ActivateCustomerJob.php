<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membuka isolir pelanggan di router (profil paket + kick sesi) lalu mengubah status menjadi
 * `active`. Isinya dikerjakan di Tahap 06; untuk saat ini job sudah di-dispatch dari
 * MarkInvoicePaid saat pelanggan isolir-otomatis tidak lagi menunggak.
 */
final class ActivateCustomerJob implements ShouldBeUnique, ShouldQueue
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

    public function handle(): void
    {
        // Diisi di Tahap 06.
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gagal mengaktifkan pelanggan.', [
            'customer_id' => $this->customer->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
