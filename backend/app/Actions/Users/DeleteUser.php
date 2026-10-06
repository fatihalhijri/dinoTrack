<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\LastAdminGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hanya untuk akun yang belum pernah dipakai (misalnya salah buat). Akun yang punya jejak
 * audit (pembayaran yang dicatat atau activity log) dirujuk FK restrict, sehingga harus
 * dinonaktifkan lewat DeactivateUser.
 */
final class DeleteUser
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly LastAdminGuard $lastAdminGuard,
    ) {}

    public function handle(User $user, User $by): void
    {
        if ($user->is($by)) {
            throw ValidationException::withMessages(['user' => 'Anda tidak bisa menghapus akun sendiri.']);
        }

        DB::transaction(function () use ($user, $by): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->receivedPayments()->exists() || $user->activityLogs()->exists()) {
                throw ValidationException::withMessages([
                    'user' => 'User sudah punya jejak aktivitas sehingga tidak bisa dihapus. Nonaktifkan sebagai gantinya.',
                ]);
            }

            $this->lastAdminGuard->ensureNotLastAdmin($user, 'user', 'Admin aktif terakhir tidak bisa dihapus.');

            $this->logger->log('user.deleted', null, $by, [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

            $user->delete();
        });
    }
}
