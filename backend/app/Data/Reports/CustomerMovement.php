<?php

declare(strict_types=1);

namespace App\Data\Reports;

use Carbon\CarbonImmutable;

/**
 * Jumlah pelanggan (bukan jumlah kejadian) yang dipasang, berhenti, dan diisolir dalam rentang
 * tanggal (inklusif).
 */
final readonly class CustomerMovement
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $newCustomers,
        public int $terminatedCustomers,
        public int $isolatedCustomers,
    ) {}

    /**
     * @return array{from: string, to: string, new_customers: int, terminated_customers: int, isolated_customers: int}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'new_customers' => $this->newCustomers,
            'terminated_customers' => $this->terminatedCustomers,
            'isolated_customers' => $this->isolatedCustomers,
        ];
    }
}
