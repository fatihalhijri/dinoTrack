<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Services\Reports\ReportService;
use App\Support\SearchTerm;
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
     * Ringkasan dashboard memuat pendapatan dan tunggakan, jadi cache-nya dihapus setiap ada perubahan.
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(ReportService::class)->forgetDashboardSummary());
    }

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
     * Tunggakan untuk laporan: masih harus dibayar dan jatuh temponya sudah lewat (`due_at < hari
     * ini`), termasuk yang belum sempat ditandai overdue oleh scheduler. Kolom dikualifikasi agar
     * aman dipakai bersama join ke customers, dan due_at dibandingkan langsung agar index terpakai.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pastDue(Builder $query, CarbonImmutable $today): void
    {
        $query->whereIn($query->qualifyColumn('status'), InvoiceStatus::outstanding())
            ->where($query->qualifyColumn('due_at'), '<', $today->toDateString());
    }

    /**
     * "Jatuh tempo minggu ini" (docs/04 "Laporan"): masih harus dibayar dengan `due_at` hari ini
     * sampai H+6, bukan minggu kalender. Dipakai ringkasan dashboard dan filter daftar tagihan.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueSoon(Builder $query, CarbonImmutable $today): void
    {
        $query->whereIn('status', InvoiceStatus::outstanding())
            ->whereBetween('due_at', [$today->toDateString(), $today->addDays(ReportService::DUE_SOON_DAYS - 1)->toDateString()]);
    }

    /**
     * Filter halaman daftar tagihan. Periode `YYYY-MM` dicocokkan dengan bulan `period_start`.
     *
     * @param  Builder<self>  $query
     * @param  array{search?: string|null, status?: InvoiceStatus|null, period?: string|null, customer_id?: int|null, due_soon?: bool}  $filters
     */
    #[Scope]
    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $period = $filters['period'] ?? null;
        $customerId = $filters['customer_id'] ?? null;
        $dueSoon = $filters['due_soon'] ?? false;

        $query
            ->when($search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $pattern = SearchTerm::contains((string) $search);
                $query->where('number', 'like', $pattern)
                    ->orWhereHas('customer', fn (Builder $query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern));
            }))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($period !== null, function (Builder $query) use ($period): void {
                $month = CarbonImmutable::parse($period.'-01');
                $query->whereBetween('period_start', [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()]);
            })
            ->when($customerId !== null, fn (Builder $query) => $query->where('customer_id', $customerId))
            ->when($dueSoon, fn (Builder $query) => $query->dueSoon(today()));
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
