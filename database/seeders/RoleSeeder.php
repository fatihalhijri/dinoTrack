<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role dasar. Permission per role ditambahkan di Tahap 02.
 */
class RoleSeeder extends Seeder
{
    /** @var list<string> */
    public const array ROLES = ['admin', 'kasir', 'teknisi'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
