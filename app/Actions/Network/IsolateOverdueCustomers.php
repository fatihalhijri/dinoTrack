<?php

declare(strict_types=1);

namespace App\Actions\Network;

use App\Enums\IsolationReason;
use App\Jobs\IsolateCustomerJob;
use App\Models\Customer;
use App\Support\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Menjadwalkan isolir otomatis untuk pelanggan `active` yang punya tunggakan lewat masa
 * toleransi (`due_at + grace_days < hari ini`). Tidak melakukan apa pun jika
 * `billing.auto_isolate` dimatikan. Syarat dicek ulang oleh job saat berjalan.
 */
final class IsolateOverdueCustomers
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @return int jumlah pelanggan yang dijadwalkan untuk diisolir
     */
    public function handle(CarbonImmutable $today): int
    {
        if (! $this->settings->autoIsolate()) {
            return 0;
        }

        $graceDays = $this->settings->graceDays();
        $queued = 0;

        Customer::query()
            ->active()
            ->whereHas('invoices', fn (Builder $query) => $query->pastGracePeriod($today, $graceDays))
            ->chunkById(200, function (Collection $customers) use ($today, &$queued): void {
                foreach ($customers as $customer) {
                    IsolateCustomerJob::dispatch($customer, IsolationReason::Overdue, $today);
                    $queued++;
                }
            });

        return $queued;
    }
}
