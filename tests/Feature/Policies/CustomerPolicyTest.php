<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;

test('memberi akses pelanggan sesuai role', function (string $ability, Model|string $target, Role $role, bool $allowed) {
    $user = userWithRole($role);

    expect($user->can($ability, $target))->toBe($allowed);
})->with(policyMatrix(Customer::class, [
    'viewAny' => [Role::Admin, Role::Kasir, Role::Teknisi],
    'view' => [Role::Admin, Role::Kasir, Role::Teknisi],
    'create' => [Role::Admin, Role::Teknisi],
    'update' => [Role::Admin],
    'delete' => [Role::Admin],
    'activate' => [Role::Admin, Role::Kasir],
    'terminate' => [Role::Admin],
    'reactivate' => [Role::Admin],
    'isolate' => [Role::Admin],
]));
