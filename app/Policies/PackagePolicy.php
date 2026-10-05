<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Package;
use App\Models\User;

/**
 * Larangan menghapus paket yang masih dipakai dijaga di Action, bukan di sini.
 */
class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permission::PackagesView);
    }

    public function view(User $user, Package $package): bool
    {
        return $user->checkPermissionTo(Permission::PackagesView);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permission::PackagesManage);
    }

    public function update(User $user, Package $package): bool
    {
        return $user->checkPermissionTo(Permission::PackagesManage);
    }

    public function delete(User $user, Package $package): bool
    {
        return $user->checkPermissionTo(Permission::PackagesManage);
    }
}
