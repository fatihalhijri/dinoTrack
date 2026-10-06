<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Models\Customer;
use Carbon\CarbonImmutable;

/**
 * Aturan isolir dan aktivasi otomatis (docs/04 "Status pelanggan"), dipakai bersama oleh
 * pembayaran, pembatalan invoice, scheduler isolir, dan job router.
 */
final class IsolationRules
{
    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Invoice `unpaid`/`overdue` dengan `due_at + grace_days < hari ini`.
     */
    public function hasArrearsPastGrace(Customer $customer, CarbonImmutable $today): bool
    {
        return $customer->invoices()->pastGracePeriod($today, $this->settings->graceDays())->exists();
    }

    public function shouldAutoIsolate(Customer $customer, CarbonImmutable $today): bool
    {
        return $customer->status === CustomerStatus::Active
            && $this->settings->autoIsolate()
            && $this->hasArrearsPastGrace($customer, $today);
    }

    /**
     * Hanya isolir otomatis yang dibuka otomatis (K8). Tagihan yang belum lewat toleransi
     * tidak menghalangi.
     */
    public function shouldAutoActivate(Customer $customer, CarbonImmutable $today): bool
    {
        return $customer->status === CustomerStatus::Isolated
            && $customer->isolation_reason === IsolationReason::Overdue
            && $this->settings->autoActivate()
            && ! $this->hasArrearsPastGrace($customer, $today);
    }
}
