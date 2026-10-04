<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\GatewayNotification;
use App\Data\PaymentChargeResult;
use App\Models\Invoice;

interface PaymentGateway
{
    public function createQrisCharge(Invoice $invoice): PaymentChargeResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotification(array $payload): bool;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function parseNotification(array $payload): GatewayNotification;

    public function checkStatus(string $orderId): GatewayNotification;
}
