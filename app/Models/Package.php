<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SearchTerm;
use Carbon\CarbonImmutable;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $speed_label
 * @property int $price
 * @property string $mikrotik_profile
 * @property bool $is_active
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'speed_label', 'price', 'mikrotik_profile', 'is_active', 'description'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @param  array{search?: string|null, is_active?: bool|null}  $filters
     */
    #[Scope]
    protected function applyFilters(Builder $query, array $filters): void
    {
        $search = $filters['search'] ?? null;
        $isActive = $filters['is_active'] ?? null;

        $query
            ->when($search !== null, fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $pattern = SearchTerm::contains((string) $search);
                $query->where('name', 'like', $pattern)->orWhere('mikrotik_profile', 'like', $pattern);
            }))
            ->when($isActive !== null, fn (Builder $query) => $query->where('is_active', $isActive));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
