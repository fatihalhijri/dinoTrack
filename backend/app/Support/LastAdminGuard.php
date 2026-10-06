<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Mencegah aplikasi kehilangan admin aktif terakhir (tidak ada lagi yang bisa mengelola user).
 * Dipanggil di dalam transaksi: baris admin aktif dikunci agar dua admin yang saling
 * menonaktifkan bersamaan tidak sama-sama lolos.
 */
final class LastAdminGuard
{
    /**
     * @throws ValidationException jika $user adalah satu-satunya admin aktif
     */
    public function ensureNotLastAdmin(User $user, string $field, string $message): void
    {
        if (! $user->hasRole(Role::Admin) || $user->isDeactivated()) {
            return;
        }

        $otherActiveAdmins = User::query()
            ->role(Role::Admin)
            ->active()
            ->whereKeyNot($user->id)
            ->lockForUpdate()
            ->count();

        if ($otherActiveAdmins === 0) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
