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
}
