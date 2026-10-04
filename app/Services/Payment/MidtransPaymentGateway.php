<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Data\GatewayNotification;
use App\Data\PaymentChargeResult;
use App\Exceptions\NotImplementedException;
use App\Models\Invoice;

final class MidtransPaymentGateway implements PaymentGateway
{
    public function createQrisCharge(Invoice $invoice): PaymentChargeResult
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }

    public function verifyNotification(array $payload): bool
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }

    public function parseNotification(array $payload): GatewayNotification
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }

    public function checkStatus(string $orderId): GatewayNotification
    {
        throw NotImplementedException::for(self::class, __FUNCTION__);
    }
}
