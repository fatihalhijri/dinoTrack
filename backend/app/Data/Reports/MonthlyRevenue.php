<?php

declare(strict_types=1);

namespace App\Data\Reports;

/**
 * Pendapatan satu bulan dari pembayaran normal (bukan anomali), berdasarkan bulan `paid_at`.
 */
final readonly class MonthlyRevenue
{
    /**
     * @param  int  $month  1–12
     * @param  array<string, int>  $byMethod  nilai PaymentMethod => rupiah; semua metode selalu ada
     */
    public function __construct(
        public int $month,
        public array $byMethod,
        public int $total,
        public int $paymentCount,
    ) {}
}
