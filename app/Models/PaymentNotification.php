<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PaymentNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $gateway
 * @property string $order_id
 * @property string $transaction_status
 * @property array<string, mixed> $payload
 * @property bool $signature_valid
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['gateway', 'order_id', 'transaction_status', 'payload', 'signature_valid', 'processed_at'])]
class PaymentNotification extends Model
{
    /** @use HasFactory<PaymentNotificationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
