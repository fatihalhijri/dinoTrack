<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Paket nonaktif tidak bisa dipilih untuk pelanggan baru atau ganti paket,
 * tetapi subscription yang sudah memakainya tetap berjalan.
 */
final class DeactivatePackage
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Package $package, ?User $by = null): Package
    {
        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'package' => 'Paket sudah nonaktif.',
            ]);
        }

        return DB::transaction(function () use ($package, $by): Package {
            $package->update(['is_active' => false]);
            $this->logger->log('package.deactivated', $package, $by);

            return $package;
        });
    }
}
