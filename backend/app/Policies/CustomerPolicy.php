<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

/**
 * Hanya mengecek permission. Syarat status (misalnya hanya pelanggan `pending` yang bisa
 * diaktifkan) dijaga di Action karena admin melewati policy lewat Gate::before.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permission::CustomersView);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->checkPermissionTo(Permission::CustomersView);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permission::CustomersCreate);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->checkPermissionTo(Permission::CustomersUpdate);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->checkPermissionTo(Permission::CustomersDelete);
    }

    /**
     * Menandai pelanggan `pending` sebagai terpasang.
     */
    public function activate(User $user, Customer $customer): bool
    {
        return $user->checkPermissionTo(Permission::CustomersActivate);
    }

    public function terminate(User $user, Customer $customer): bool
    {
        return $user->checkPermissionTo(Permission::CustomersTerminate);
    }

    /**
     * Mengembalikan pelanggan `terminated` ke `pending` dengan subscription baru.
     */
    public function reactivate(User $user, Customer $customer): bool
    {
        return $user->checkPermissionTo(Permission::CustomersTerminate);
    }

    /**
     * Isolir dan buka isolir manual (bukan yang otomatis karena tunggakan).
     */
    public function isolate(User $user, Customer $customer): bool
    {
        return $user->checkPermissionTo(Permission::CustomersIsolate);
    }
}
