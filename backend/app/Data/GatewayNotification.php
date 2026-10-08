<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PaymentChargeStatus;
use Carbon\CarbonImmutable;

final readonly class GatewayNotification
{
    /** Status setelah lunas yang menandakan uang dikembalikan atau ditarik. */
    private const array REVERSAL_STATUSES = ['refund', 'partial_refund', 'chargeback', 'partial_chargeback'];

    /**
     * @param  PaymentChargeStatus|null  $status  null jika status gateway tidak mengubah charge (refund, chargeback, atau tidak dikenal)
     * @param  string  $transactionStatus  status mentah dari gateway (huruf kecil)
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $orderId,
        public ?PaymentChargeStatus $status,
        public int $grossAmount,
        public ?string $reference = null,
        public array $payload = [],
        public string $transactionStatus = '',
        public ?CarbonImmutable $paidAt = null,
    ) {}

    public function isSettled(): bool
    {
        return $this->status === PaymentChargeStatus::Settled;
    }

    public function isReversal(): bool
    {
        return in_array($this->transactionStatus, self::REVERSAL_STATUSES, true);
    }
}
