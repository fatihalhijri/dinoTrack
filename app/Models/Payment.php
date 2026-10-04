<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int|null $payment_charge_id
 * @property PaymentMethod $method
 * @property int $amount
 * @property CarbonImmutable $paid_at
 * @property string|null $reference
 * @property int|null $received_by
 * @property string|null $notes
 * @property PaymentReviewStatus $review_status
 * @property string|null $review_note
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'invoice_id', 'payment_charge_id', 'method', 'amount', 'paid_at', 'reference', 'received_by',
    'notes', 'review_status', 'review_note',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'review_status' => PaymentReviewStatus::None->value,
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<PaymentCharge, $this>
     */
    public function paymentCharge(): BelongsTo
    {
        return $this->belongsTo(PaymentCharge::class);
    }

    /**
     * Kasir yang menerima pembayaran manual.
     *
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function needsReview(Builder $query): void
    {
        $query->where('review_status', PaymentReviewStatus::NeedsReview);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'review_status' => PaymentReviewStatus::class,
        ];
    }
}
