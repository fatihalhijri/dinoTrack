<?php

declare(strict_types=1);

namespace App\Data\Reports;

use Carbon\CarbonImmutable;

/**
 * Ringkasan dashboard. Disimpan di cache sebagai array karena cache aplikasi tidak
 * meng-unserialize objek (`cache.serializable_classes = false`).
 */
final readonly class DashboardSummary
{
    /**
     * @param  CarbonImmutable  $generatedAt  waktu angka dihitung (bisa sampai 5 menit lalu karena cache)
     */
    public function __construct(
        public int $revenueThisMonth,
        public int $paymentsThisMonth,
        public int $outstandingAmount,
        public int $outstandingInvoices,
        public int $activeCustomers,
        public int $isolatedCustomers,
        public int $pendingCustomers,
        public int $dueThisWeekAmount,
        public int $dueThisWeekInvoices,
        public int $paymentsNeedingReview,
        public int $paymentsNeedingReviewAmount,
        public int $customersWithNetworkError,
        public CarbonImmutable $generatedAt,
    ) {}

    /**
     * @return array{
     *     revenue_this_month: int, payments_this_month: int, outstanding_amount: int, outstanding_invoices: int,
     *     active_customers: int, isolated_customers: int, pending_customers: int, due_this_week_amount: int,
     *     due_this_week_invoices: int, payments_needing_review: int, payments_needing_review_amount: int,
     *     customers_with_network_error: int, generated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'revenue_this_month' => $this->revenueThisMonth,
            'payments_this_month' => $this->paymentsThisMonth,
            'outstanding_amount' => $this->outstandingAmount,
            'outstanding_invoices' => $this->outstandingInvoices,
            'active_customers' => $this->activeCustomers,
            'isolated_customers' => $this->isolatedCustomers,
            'pending_customers' => $this->pendingCustomers,
            'due_this_week_amount' => $this->dueThisWeekAmount,
            'due_this_week_invoices' => $this->dueThisWeekInvoices,
            'payments_needing_review' => $this->paymentsNeedingReview,
            'payments_needing_review_amount' => $this->paymentsNeedingReviewAmount,
            'customers_with_network_error' => $this->customersWithNetworkError,
            'generated_at' => $this->generatedAt->toIso8601String(),
        ];
    }

    /**
     * @param  array{
     *     revenue_this_month: int, payments_this_month: int, outstanding_amount: int, outstanding_invoices: int,
     *     active_customers: int, isolated_customers: int, pending_customers: int, due_this_week_amount: int,
     *     due_this_week_invoices: int, payments_needing_review: int, payments_needing_review_amount: int,
     *     customers_with_network_error: int, generated_at: string
     * }  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            revenueThisMonth: $values['revenue_this_month'],
            paymentsThisMonth: $values['payments_this_month'],
            outstandingAmount: $values['outstanding_amount'],
            outstandingInvoices: $values['outstanding_invoices'],
            activeCustomers: $values['active_customers'],
            isolatedCustomers: $values['isolated_customers'],
            pendingCustomers: $values['pending_customers'],
            dueThisWeekAmount: $values['due_this_week_amount'],
            dueThisWeekInvoices: $values['due_this_week_invoices'],
            paymentsNeedingReview: $values['payments_needing_review'],
            paymentsNeedingReviewAmount: $values['payments_needing_review_amount'],
            customersWithNetworkError: $values['customers_with_network_error'],
            generatedAt: CarbonImmutable::parse($values['generated_at']),
        );
    }
}
