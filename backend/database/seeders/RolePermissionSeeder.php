<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyamakan role dan permission di database dengan matriks di Role::permissions().
 * Aman dijalankan ulang; perubahan matriks manual di database akan ditimpa.
 */
class RolePermissionSeeder extends Seeder
{
    private const string GUARD = 'web';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, self::GUARD);
        }

        PermissionModel::query()
            ->where('guard_name', self::GUARD)
            ->whereNotIn('name', Permission::values())
            ->delete();

        // DatabaseSeeder memakai WithoutModelEvents, sehingga cache spatie tidak ter-reset otomatis.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, self::GUARD)
                ->syncPermissions(array_map(
                    fn (Permission $permission): string => $permission->value,
                    $role->permissions(),
                ));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
