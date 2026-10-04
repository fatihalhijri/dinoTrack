<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PaymentChargeStatus;
use Carbon\CarbonInterface;

final readonly class PaymentChargeResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $orderId,
        public int $amount,
        public PaymentChargeStatus $status,
        public ?string $qrString = null,
        public ?string $qrUrl = null,
        public ?CarbonInterface $expiresAt = null,
        public array $rawResponse = [],
    ) {}
}
