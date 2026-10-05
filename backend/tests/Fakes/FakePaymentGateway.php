<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\PaymentGateway;
use App\Data\GatewayNotification;
use App\Data\PaymentChargeResult;
use App\Enums\PaymentChargeStatus;
use App\Models\Invoice;

final class FakePaymentGateway implements PaymentGateway
{
    use RecordsCalls;

    public bool $signatureValid = true;

    private int $chargeSequence = 0;

    private ?GatewayNotification $notification = null;

    /** @var array<string, GatewayNotification> */
    private array $statuses = [];

    /** Atur hasil parseNotification() dan checkStatus() berikutnya. */
    public function respondWith(GatewayNotification $notification): static
    {
        $this->notification = $notification;
        $this->statuses[$notification->orderId] = $notification;

        return $this;
    }

    public function createQrisCharge(Invoice $invoice, string $orderId): PaymentChargeResult
    {
        $this->record(__FUNCTION__, [$invoice, $orderId]);

        $sequence = ++$this->chargeSequence;

        return new PaymentChargeResult(
            orderId: $orderId,
            amount: (int) $invoice->getAttribute('total'),
            status: PaymentChargeStatus::Pending,
            qrString: "fake-qr-{$sequence}",
            qrUrl: "https://fake.test/qr/{$sequence}.png",
            expiresAt: now()->addMinutes(15),
        );
    }

    public function verifyNotification(array $payload): bool
    {
        $this->record(__FUNCTION__, [$payload]);

        return $this->signatureValid;
    }

    public function parseNotification(array $payload): GatewayNotification
    {
        $this->record(__FUNCTION__, [$payload]);

        return $this->notification ?? new GatewayNotification(
            orderId: (string) ($payload['order_id'] ?? 'FAKE-1-1'),
            status: PaymentChargeStatus::Settled,
            grossAmount: (int) ($payload['gross_amount'] ?? 0),
            reference: (string) ($payload['transaction_id'] ?? 'fake-reference'),
            payload: $payload,
            transactionStatus: 'settlement',
        );
    }

    public function checkStatus(string $orderId): GatewayNotification
    {
        $this->record(__FUNCTION__, [$orderId]);

        return $this->statuses[$orderId] ?? new GatewayNotification(
            orderId: $orderId,
            status: PaymentChargeStatus::Pending,
            grossAmount: 0,
            transactionStatus: 'pending',
        );
    }
}
