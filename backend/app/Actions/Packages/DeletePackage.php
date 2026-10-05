<?php

declare(strict_types=1);

namespace App\Actions\Packages;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeletePackage
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Paket yang pernah dipakai (termasuk riwayat dan rencana ganti paket) ditolak dengan
     * pesan jelas, bukan dibiarkan gagal di foreign key.
     */
    public function handle(Package $package, ?User $by = null): void
    {
        $isUsed = Subscription::query()
            ->where('package_id', $package->id)
            ->orWhere('next_package_id', $package->id)
            ->exists();

        if ($isUsed) {
            throw ValidationException::withMessages([
                'package' => 'Paket masih dipakai pelanggan dan tidak bisa dihapus. Nonaktifkan paket sebagai gantinya.',
            ]);
        }

        DB::transaction(function () use ($package, $by): void {
            $package->delete();
            $this->logger->log('package.deleted', $package, $by, ['name' => $package->name]);
        });
    }
}
