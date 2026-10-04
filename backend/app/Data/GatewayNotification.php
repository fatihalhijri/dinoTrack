<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PaymentChargeStatus;

final readonly class GatewayNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $orderId,
        public PaymentChargeStatus $status,
        public int $grossAmount,
        public ?string $reference = null,
        public array $payload = [],
    ) {}

    public function isSettled(): bool
    {
        return $this->status === PaymentChargeStatus::Settled;
    }
}
