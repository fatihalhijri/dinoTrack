<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

test('memberi akses manajemen user hanya untuk admin', function (string $ability, Model|string $target, Role $role, bool $allowed) {
    $user = userWithRole($role);

    expect($user->can($ability, $target))->toBe($allowed);
})->with(policyMatrix(User::class, [
    'viewAny' => [Role::Admin],
    'view' => [Role::Admin],
    'create' => [Role::Admin],
    'update' => [Role::Admin],
    'delete' => [Role::Admin],
]));
