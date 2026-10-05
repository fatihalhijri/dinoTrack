<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class CreatePackage
{
    public const array FIELDS = ['name', 'speed_label', 'price', 'mikrotik_profile', 'description'];

    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{name: string, speed_label: string, price: int, mikrotik_profile: string, description?: string|null}  $attributes
     */
    public function handle(array $attributes, ?User $by = null): Package
    {
        return DB::transaction(function () use ($attributes, $by): Package {
            $package = Package::query()->create([...Arr::only($attributes, self::FIELDS), 'is_active' => true]);

            $this->logger->log('package.created', $package, $by, [
                'name' => $package->name,
                'price' => $package->price,
                'mikrotik_profile' => $package->mikrotik_profile,
            ]);

            return $package;
        });
    }
}
