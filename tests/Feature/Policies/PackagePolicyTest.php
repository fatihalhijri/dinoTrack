<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Package;
use Illuminate\Database\Eloquent\Model;

test('memberi akses paket sesuai role', function (string $ability, Model|string $target, Role $role, bool $allowed) {
    $user = userWithRole($role);

    expect($user->can($ability, $target))->toBe($allowed);
})->with(policyMatrix(Package::class, [
    'viewAny' => [Role::Admin, Role::Kasir, Role::Teknisi],
    'view' => [Role::Admin, Role::Kasir, Role::Teknisi],
    'create' => [Role::Admin],
    'update' => [Role::Admin],
    'delete' => [Role::Admin],
]));
