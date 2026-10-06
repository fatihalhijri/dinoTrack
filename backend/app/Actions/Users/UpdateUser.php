<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\LastAdminGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mengubah nama, email, role, dan (opsional) password pegawai. Email yang diubah admin tetap
 * terverifikasi, sama seperti saat akun dibuat.
 */
final class UpdateUser
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly LastAdminGuard $lastAdminGuard,
    ) {}

    /**
     * @param  array{name: string, email: string, password?: string|null}  $attributes  password kosong = tidak diubah
     */
    public function handle(User $user, array $attributes, Role $role, User $by): User
    {
        return DB::transaction(function () use ($user, $attributes, $role, $by): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $previousRole = $this->roleOf($user);
            $isRoleChanged = $previousRole !== $role;

            if ($isRoleChanged && $user->is($by)) {
                throw ValidationException::withMessages(['role' => 'Anda tidak bisa mengubah role akun sendiri.']);
            }

            if ($isRoleChanged && $role !== Role::Admin) {
                $this->lastAdminGuard->ensureNotLastAdmin($user, 'role', 'Harus ada minimal satu admin aktif.');
            }

            $user->fill(['name' => $attributes['name'], 'email' => $attributes['email']]);

            if (($attributes['password'] ?? null) !== null && $attributes['password'] !== '') {
                $user->password = $attributes['password'];
            }

            $changes = $this->logger->pendingChanges($user);

            if ($isRoleChanged) {
                $changes['role'] = [$previousRole?->value, $role->value];
            }

            if ($changes === []) {
                return $user;
            }

            $user->save();

            if ($isRoleChanged) {
                $user->syncRoles([$role]);
            }

            $this->logger->log('user.updated', $user, $by, ['changes' => $changes]);

            return $user;
        });
    }

    private function roleOf(User $user): ?Role
    {
        $name = $user->getRoleNames()->first();

        return is_string($name) ? Role::tryFrom($name) : null;
    }
}
