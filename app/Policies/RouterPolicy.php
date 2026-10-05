<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Router;
use App\Models\User;

class RouterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permission::RoutersManage);
    }

    public function view(User $user, Router $router): bool
    {
        return $user->checkPermissionTo(Permission::RoutersManage);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permission::RoutersManage);
    }

    public function update(User $user, Router $router): bool
    {
        return $user->checkPermissionTo(Permission::RoutersManage);
    }

    public function delete(User $user, Router $router): bool
    {
        return $user->checkPermissionTo(Permission::RoutersManage);
    }

    public function testConnection(User $user, Router $router): bool
    {
        return $user->checkPermissionTo(Permission::RoutersManage);
    }
}
