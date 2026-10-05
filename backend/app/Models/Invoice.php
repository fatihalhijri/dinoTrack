<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property int $customer_id
 * @property int $subscription_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property CarbonImmutable|null $billed_period_start kolom generated: period_start jika tidak dibatalkan; jangan diisi aplikasi
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $due_at
 * @property int $subtotal
 * @property int $discount
 * @property int $penalty
 * @property int $total
 * @property InvoiceStatus $status
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancelled_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'number', 'customer_id', 'subscription_id', 'period_start', 'period_end', 'issued_at', 'due_at',
    'subtotal', 'discount', 'penalty', 'total', 'status', 'paid_at', 'cancelled_at', 'cancelled_reason',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * Diskon dan denda belum dipakai di v1 sehingga selalu 0.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'discount' => 0,
        'penalty' => 0,
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<PaymentCharge, $this>
     */
    public function paymentCharges(): HasMany
    {
        return $this->hasMany(PaymentCharge::class);
    }

    /**
     * @return HasMany<MessageLog, $this>
     */
    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    /**
     * Invoice yang masih harus dibayar (unpaid atau overdue).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function outstanding(Builder $query): void
    {
        $query->whereIn('status', InvoiceStatus::outstanding());
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Overdue);
    }

    /**
     * Tunggakan yang sudah lewat masa toleransi (`due_at + grace_days < hari ini`): dasar isolir
     * otomatis, dan penghalang aktivasi otomatis setelah pembayaran.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pastGracePeriod(Builder $query, CarbonImmutable $today, int $graceDays): void
    {
        $query->whereIn('status', InvoiceStatus::outstanding())
            ->whereDate('due_at', '<', $today->subDays($graceDays)->toDateString());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'billed_period_start' => 'date:Y-m-d',
            'issued_at' => 'date:Y-m-d',
            'due_at' => 'date:Y-m-d',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'penalty' => 'integer',
            'total' => 'integer',
            'status' => InvoiceStatus::class,
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
