<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Models\Subscription;
use App\Support\BillingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generator harian (catch-up): menagih setiap periode yang sudah dimulai tetapi belum punya
 * invoice untuk semua pelanggan active/isolated. Aman dijalankan ulang di hari yang sama.
 */
final class GenerateMonthlyInvoices
{
    public function __construct(
        private readonly GenerateInvoiceForSubscription $generateInvoice,
    ) {}

    /**
     * @return array{created: int, skipped: int, failed: int, failed_subscription_ids: list<int>}
     */
    public function handle(CarbonImmutable $today): array
    {
        $result = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'failed_subscription_ids' => []];

        Subscription::query()
            ->current()
            ->whereNotNull('starts_at')
            ->whereHas('customer', fn (Builder $query) => $query->billable())
            ->withMax('invoices', 'period_end')
            ->chunkById(100, function (Collection $subscriptions) use ($today, &$result): void {
                foreach ($subscriptions as $subscription) {
                    $this->generateFor($subscription, $today, $result);
                }
            });

        return $result;
    }

    /**
     * Satu subscription yang gagal tidak menghentikan subscription lain; periode berikutnya
     * milik subscription yang gagal ditunda ke run berikutnya agar urutannya tetap benar.
     *
     * @param  array{created: int, skipped: int, failed: int, failed_subscription_ids: list<int>}  $result
     */
    private function generateFor(Subscription $subscription, CarbonImmutable $today, array &$result): void
    {
        // Sudah difilter whereNotNull di query; pengecekan ini menjaga tipe untuk BillingPeriod.
        if ($subscription->starts_at === null) {
            return;
        }

        /** @var string|null $lastPeriodEnd */
        $lastPeriodEnd = $subscription->getAttribute('invoices_max_period_end');

        $periods = BillingPeriod::due(
            $subscription->starts_at,
            $subscription->billing_day,
            $lastPeriodEnd === null ? null : CarbonImmutable::parse($lastPeriodEnd),
            $today,
        );

        foreach ($periods as $period) {
            try {
                $invoice = $this->generateInvoice->handle($subscription, $period, $today);
            } catch (Throwable $exception) {
                Log::error('Gagal menerbitkan tagihan subscription.', [
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer_id,
                    'period_start' => $period->start->toDateString(),
                    'exception' => $exception,
                ]);
                $result['failed']++;
                $result['failed_subscription_ids'][] = $subscription->id;

                return;
            }

            $invoice === null ? $result['skipped']++ : $result['created']++;
        }
    }
}
