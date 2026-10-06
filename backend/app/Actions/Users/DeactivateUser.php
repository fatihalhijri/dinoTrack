<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\LastAdminGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pegawai yang keluar dinonaktifkan, bukan dihapus, agar jejak auditnya utuh. Session yang
 * masih berjalan diputus oleh middleware EnsureUserIsActive; token "ingat saya" diganti.
 */
final class DeactivateUser
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly LastAdminGuard $lastAdminGuard,
    ) {}

    public function handle(User $user, User $by): User
    {
        if ($user->is($by)) {
            throw ValidationException::withMessages(['user' => 'Anda tidak bisa menonaktifkan akun sendiri.']);
        }

        return DB::transaction(function () use ($user, $by): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->isDeactivated()) {
                throw ValidationException::withMessages(['user' => 'Akun sudah nonaktif.']);
            }

            $this->lastAdminGuard->ensureNotLastAdmin($user, 'user', 'Admin aktif terakhir tidak bisa dinonaktifkan.');

            $user->deactivated_at = now();
            $user->setRememberToken(Str::random(60));
            $user->save();

            $this->logger->log('user.deactivated', $user, $by);

            return $user;
        });
    }
}
