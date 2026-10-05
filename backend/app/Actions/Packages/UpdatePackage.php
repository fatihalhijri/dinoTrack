<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Harga baru hanya berlaku untuk langganan baru; harga subscription yang sudah ada terkunci.
 * Perubahan profil Mikrotik tidak dikirim ke router di sini.
 */
final class UpdatePackage
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{name?: string, speed_label?: string, price?: int, mikrotik_profile?: string, is_active?: bool, description?: string|null}  $attributes
     */
    public function handle(Package $package, array $attributes, ?User $by = null): Package
    {
        return DB::transaction(function () use ($package, $attributes, $by): Package {
            $package->fill(Arr::only($attributes, [...CreatePackage::FIELDS, 'is_active']));
            $changes = $this->logger->pendingChanges($package);

            if ($changes === []) {
                return $package;
            }

            $package->save();
            $this->logger->log('package.updated', $package, $by, ['changes' => $changes]);

            return $package;
        });
    }
}
