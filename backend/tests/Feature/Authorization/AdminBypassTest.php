<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

it('mengizinkan admin walau role admin tidak punya permission di database', function () {
    $admin = userWithRole(Role::Admin);
    RoleModel::findByName(Role::Admin->value)->syncPermissions([]);

    expect($admin->fresh()->can('cancel', new Invoice))->toBeTrue();
});

it('menolak user tanpa role untuk semua aksi', function (string $ability, Closure $target) {
    $user = User::factory()->create();

    expect($user->can($ability, $target()))->toBeFalse();
})->with([
    'lihat pelanggan' => ['viewAny', fn (): string => Customer::class],
    'lihat paket' => ['viewAny', fn (): string => Package::class],
    'kelola router' => ['viewAny', fn (): string => Router::class],
    'lihat tagihan' => ['view', fn (): Invoice => new Invoice],
    'catat pembayaran' => ['create', fn (): string => Payment::class],
    'kelola user' => ['viewAny', fn (): string => User::class],
]);
