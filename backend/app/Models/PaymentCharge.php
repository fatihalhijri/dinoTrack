<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentChargeStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentChargeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $attempt
 * @property string $gateway
 * @property string $order_id
 * @property int $amount
 * @property string|null $qr_string
 * @property string|null $qr_url
 * @property PaymentChargeStatus $status
 * @property CarbonImmutable|null $expires_at
 * @property array<string, mixed>|null $raw_response
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'invoice_id', 'attempt', 'gateway', 'order_id', 'amount', 'qr_string', 'qr_url', 'status', 'expires_at', 'raw_response',
])]
class PaymentCharge extends Model
{
    /** @use HasFactory<PaymentChargeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Normalnya satu, tetapi charge yang dibayar ganda menghasilkan pembayaran anomali tambahan.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Charge tanpa QR adalah percobaan yang belum (atau tidak pernah) mendapat jawaban gateway.
     */
    public function hasQr(): bool
    {
        return $this->qr_url !== null || $this->qr_string !== null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', PaymentChargeStatus::Pending);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'amount' => 'integer',
            'status' => PaymentChargeStatus::class,
            'expires_at' => 'datetime',
            'raw_response' => 'array',
        ];
    }
}
