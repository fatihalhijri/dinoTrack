<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Router;
use Illuminate\Database\Eloquent\Model;

test('memberi akses router hanya untuk admin', function (string $ability, Model|string $target, Role $role, bool $allowed) {
    $user = userWithRole($role);

    expect($user->can($ability, $target))->toBe($allowed);
})->with(policyMatrix(Router::class, [
    'viewAny' => [Role::Admin],
    'view' => [Role::Admin],
    'create' => [Role::Admin],
    'update' => [Role::Admin],
    'delete' => [Role::Admin],
    'testConnection' => [Role::Admin],
]));
