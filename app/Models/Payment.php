<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewStatus;
use App\Services\Reports\ReportService;
use App\Support\SearchTerm;
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
     * Ringkasan dashboard memuat pendapatan dan tunggakan, jadi cache-nya dihapus setiap ada perubahan.
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(ReportService::class)->forgetDashboardSummary());
    }

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
     * Filter halaman daftar pembayaran; rentang tanggal mengikuti `paid_at` (inklusif) dan
     * pelanggan mengikuti pemilik invoice.
     *
     * @param  Builder<self>  $query
     * @param  array{search?: string|null, method?: PaymentMethod|null, review_status?: PaymentReviewStatus|null, from?: CarbonImmutable|null, to?: CarbonImmutable|null, customer_id?: int|null}  $filters
     */
    #[Scope]
    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = $filters['search'] ?? null;
        $method = $filters['method'] ?? null;
        $reviewStatus = $filters['review_status'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $customerId = $filters['customer_id'] ?? null;

        $query
            ->when($search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $pattern = SearchTerm::contains((string) $search);
                $query->where('reference', 'like', $pattern)
                    ->orWhereHas('invoice', fn (Builder $query) => $query->where('number', 'like', $pattern)
                        ->orWhereHas('customer', fn (Builder $query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern)));
            }))
            ->when($method !== null, fn (Builder $query) => $query->where('method', $method))
            ->when($reviewStatus !== null, fn (Builder $query) => $query->where('review_status', $reviewStatus))
            ->when($from !== null, fn (Builder $query) => $query->where('paid_at', '>=', $from?->startOfDay()))
            ->when($to !== null, fn (Builder $query) => $query->where('paid_at', '<=', $to?->endOfDay()))
            ->when($customerId !== null, fn (Builder $query) => $query->whereRelation('invoice', 'customer_id', $customerId));
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
