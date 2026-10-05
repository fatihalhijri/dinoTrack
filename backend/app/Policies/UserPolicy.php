<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Manajemen user oleh admin. Profil sendiri diurus halaman pengaturan, bukan policy ini.
 * Larangan menghapus diri sendiri atau user yang punya jejak audit dijaga di Action.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permission::UsersManage);
    }

    public function view(User $user, User $model): bool
    {
        return $user->checkPermissionTo(Permission::UsersManage);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permission::UsersManage);
    }

    public function update(User $user, User $model): bool
    {
        return $user->checkPermissionTo(Permission::UsersManage);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->checkPermissionTo(Permission::UsersManage);
    }
}
