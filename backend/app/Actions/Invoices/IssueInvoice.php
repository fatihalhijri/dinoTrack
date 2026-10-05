<?php

declare(strict_types=1);

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoiceNotificationJob;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\BillingPeriod;
use App\Support\InvoiceNumberGenerator;
use App\Support\ProrataCalculator;
use App\Support\SettingsRepository;
use Carbon\CarbonImmutable;

/**
 * Membuat invoice satu periode dari keadaan subscription saat ini: nominal (prorata untuk
 * periode pertama yang tidak penuh), nomor, item, log, dan notifikasi.
 *
 * Tidak melakukan pengecekan apa pun; dipanggil di dalam transaksi oleh GenerateInvoiceForSubscription
 * dan ReissueInvoice yang sudah mengunci pelanggan dan memastikan periode boleh ditagih.
 */
final class IssueInvoice
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly SettingsRepository $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Subscription $subscription, BillingPeriod $period, CarbonImmutable $issuedAt, ?User $by = null): Invoice
    {
        $isFirstPeriod = $subscription->starts_at !== null && $period->start->equalTo($subscription->starts_at);
        [$amount, $description] = $this->priceFor($subscription, $period, $isFirstPeriod);

        $invoice = Invoice::query()->create([
            'number' => $this->numbers->next($issuedAt),
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'period_start' => $period->start->toDateString(),
            'period_end' => $period->end->toDateString(),
            'issued_at' => $issuedAt->toDateString(),
            'due_at' => $issuedAt->addDays($this->settings->dueDays())->toDateString(),
            'subtotal' => $amount,
            'total' => $amount,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $invoice->items()->create([
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
        ]);

        $this->logger->log('invoice.issued', $invoice, $by, [
            'number' => $invoice->number,
            'total' => $invoice->total,
            'period_start' => $period->start->toDateString(),
            'period_end' => $period->end->toDateString(),
        ]);

        SendInvoiceNotificationJob::dispatch($invoice)->afterCommit();

        return $invoice;
    }

    /**
     * @return array{int, string}
     */
    private function priceFor(Subscription $subscription, BillingPeriod $period, bool $isFirstPeriod): array
    {
        $packageName = $subscription->package()->value('name');
        $description = sprintf('%s (%s – %s)', $packageName, $this->formatDate($period->start), $this->formatDate($period->end));

        $fullPeriod = BillingPeriod::containing($period->start, $subscription->billing_day);
        $isPartial = $isFirstPeriod && $period->days() < $fullPeriod->days();

        if (! $isPartial || ! $this->settings->prorateFirstMonth()) {
            return [$subscription->price, $description];
        }

        return [
            ProrataCalculator::calculate($subscription->price, $period->days(), $fullPeriod->days()),
            sprintf('%s, prorata %d/%d hari', $description, $period->days(), $fullPeriod->days()),
        ];
    }

    private function formatDate(CarbonImmutable $date): string
    {
        return $date->translatedFormat('j M Y');
    }
}
