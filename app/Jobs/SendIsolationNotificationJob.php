<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Notifications\NotifyCustomer;
use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Enums\MessageTemplateKey;
use App\Enums\QueueName;
use App\Models\Customer;
use App\Support\SettingsRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjadwalkan pesan WhatsApp `isolated` setelah isolir berhasil di router (dari IsolateCustomer).
 *
 * Hanya untuk isolir karena tunggakan: template-nya menyebut tagihan yang belum dibayar, sedangkan
 * isolir manual bisa karena alasan lain. Invoice yang disebut adalah tunggakan lewat toleransi
 * yang paling lama; pelanggan yang sudah membayar atau diaktifkan sebelum job berjalan dilewati.
 */
#[Queue(QueueName::Notifications)]
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

    public function handle(NotifyCustomer $notify, SettingsRepository $settings): void
    {
        if ($this->customer->status !== CustomerStatus::Isolated || $this->customer->isolation_reason !== IsolationReason::Overdue) {
            return;
        }

        $invoice = $this->customer->invoices()
            ->pastGracePeriod(today(), $settings->graceDays())
            ->orderBy('due_at')
            ->orderBy('id')
            ->first();

        if ($invoice === null) {
            return;
        }

        $notify->handle($this->customer, MessageTemplateKey::Isolated, $invoice);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Gagal mengirim pemberitahuan isolir.', [
            'customer_id' => $this->customer->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
