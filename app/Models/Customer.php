<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Enums\IsolationReason;
use App\Support\SearchTerm;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $phone
 * @property string $address
 * @property string|null $odp
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int $router_id
 * @property string $pppoe_username
 * @property string|null $active_pppoe_username kolom generated: pppoe_username jika tidak di-soft-delete; jangan diisi aplikasi
 * @property CustomerStatus $status
 * @property CarbonImmutable|null $installed_at
 * @property CarbonImmutable|null $isolated_at
 * @property IsolationReason|null $isolation_reason
 * @property CarbonImmutable|null $terminated_at
 * @property CarbonImmutable|null $network_error_at perintah router terakhir gagal setelah semua percobaan; tanda untuk admin
 * @property string|null $network_error
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'code', 'name', 'phone', 'address', 'odp', 'latitude', 'longitude', 'router_id', 'pppoe_username',
    'status', 'installed_at', 'isolated_at', 'isolation_reason', 'terminated_at', 'network_error_at', 'network_error', 'notes',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Paket pelanggan diambil dari sini, bukan dari kolom di tabel customers.
     *
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->whereNull('ends_at');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<MessageLog, $this>
     */
    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', CustomerStatus::Active);
    }

    /**
     * Pelanggan yang ditagih setiap periode: pending dan terminated tidak ditagih.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function billable(Builder $query): void
    {
        $query->whereIn('status', [CustomerStatus::Active, CustomerStatus::Isolated]);
    }

    /**
     * Pelanggan yang perintah routernya gagal dan perlu diperiksa admin.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function hasNetworkError(Builder $query): void
    {
        $query->whereNotNull('network_error_at');
    }

    /**
     * Filter halaman daftar pelanggan. Paket dicocokkan dengan subscription aktif.
     *
     * @param  Builder<self>  $query
     * @param  array{search?: string|null, status?: CustomerStatus|null, package_id?: int|null, router_id?: int|null, network_error?: bool}  $filters
     */
    #[Scope]
    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = $filters['search'] ?? null;
        $status = $filters['status'] ?? null;
        $packageId = $filters['package_id'] ?? null;
        $routerId = $filters['router_id'] ?? null;

        $query
            ->when($search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $pattern = SearchTerm::contains((string) $search);
                $query->where('code', 'like', $pattern)
                    ->orWhere('name', 'like', $pattern)
                    ->orWhere('phone', 'like', $pattern)
                    ->orWhere('pppoe_username', 'like', $pattern);
            }))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($packageId !== null, fn (Builder $query) => $query->whereHas('activeSubscription', fn (Builder $query) => $query->where('package_id', $packageId)))
            ->when($routerId !== null, fn (Builder $query) => $query->where('router_id', $routerId))
            ->when($filters['network_error'] ?? false, fn (Builder $query) => $query->whereNotNull('network_error_at'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => CustomerStatus::class,
            'installed_at' => 'date:Y-m-d',
            'isolated_at' => 'datetime',
            'isolation_reason' => IsolationReason::class,
            'terminated_at' => 'datetime',
            'network_error_at' => 'datetime',
        ];
    }
}
