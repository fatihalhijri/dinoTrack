<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengaktifkan kembali paket yang dinonaktifkan agar bisa dipilih lagi untuk pelanggan baru.
 */
final class ActivatePackage
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Package $package, ?User $by = null): Package
    {
        if ($package->is_active) {
            throw ValidationException::withMessages([
                'package' => 'Paket sudah aktif.',
            ]);
        }

        return DB::transaction(function () use ($package, $by): Package {
            $package->update(['is_active' => true]);
            $this->logger->log('package.activated', $package, $by);

            return $package;
        });
    }
}
