<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\GatewayNotification;
use App\Data\PaymentChargeResult;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;

interface PaymentGateway
{
    /**
     * `order_id` ditentukan pemanggil (CreateQrisCharge) dari nomor invoice dan urutan percobaan.
     *
     * @throws PaymentGatewayException
     */
    public function createQrisCharge(Invoice $invoice, string $orderId): PaymentChargeResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyNotification(array $payload): bool;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws PaymentGatewayException
     */
    public function parseNotification(array $payload): GatewayNotification;

    /**
     * @throws PaymentGatewayException
     */
    public function checkStatus(string $orderId): GatewayNotification;
}
