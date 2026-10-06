<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim pesan WhatsApp `isolated` setelah isolir berhasil di router.
 * Pengiriman diisi di Tahap 07; job sudah di-dispatch dari IsolateCustomer.
 */
final class SendIsolationNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 30;

    public function __construct(
        public Customer $customer,
    ) {}

    public function handle(): void
    {
        // Diisi di Tahap 07.
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gagal mengirim pemberitahuan isolir.', [
            'customer_id' => $this->customer->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
