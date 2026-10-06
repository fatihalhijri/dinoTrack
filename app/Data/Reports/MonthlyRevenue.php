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

    /**
     * @return array{month: int, by_method: array<string, int>, total: int, payment_count: int}
     */
    public function toArray(): array
    {
        return [
            'month' => $this->month,
            'by_method' => $this->byMethod,
            'total' => $this->total,
            'payment_count' => $this->paymentCount,
        ];
    }
}
