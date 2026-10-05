<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('tidak membuat role atau permission ganda saat dijalankan ulang', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->seed(RolePermissionSeeder::class);

    expect(Role::count())->toBe(3)
        ->and(Permission::count())->toBe(19)
        ->and(DB::table('role_has_permissions')->count())->toBe(19 + 7 + 3);
});

it('mengembalikan matriks role yang diubah manual di database', function () {
    $this->seed(RolePermissionSeeder::class);
    $kasir = Role::findByName('kasir');
    $kasir->givePermissionTo('invoices.cancel');
    $kasir->revokePermissionTo('payments.record');

    $this->seed(RolePermissionSeeder::class);

    expect($kasir->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe([
        'customers.activate',
        'customers.view',
        'invoices.resend',
        'invoices.view',
        'packages.view',
        'payments.record',
        'payments.view',
    ]);
});

it('menghapus permission yang tidak lagi dipakai', function () {
    $this->seed(RolePermissionSeeder::class);
    Permission::findOrCreate('customers.export', 'web');

    $this->seed(RolePermissionSeeder::class);

    $this->assertDatabaseMissing('permissions', ['name' => 'customers.export']);
});
