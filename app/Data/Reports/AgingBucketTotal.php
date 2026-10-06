<?php

declare(strict_types=1);

namespace App\Data\Reports;

use App\Enums\OutstandingAgeBucket;

final readonly class AgingBucketTotal
{
    public function __construct(
        public OutstandingAgeBucket $bucket,
        public int $invoiceCount,
        public int $amount,
    ) {}

    /**
     * @return array{bucket: string, label: string, invoice_count: int, amount: int}
     */
    public function toArray(): array
    {
        return [
            'bucket' => $this->bucket->value,
            'label' => $this->bucket->label(),
            'invoice_count' => $this->invoiceCount,
            'amount' => $this->amount,
        ];
    }
}
