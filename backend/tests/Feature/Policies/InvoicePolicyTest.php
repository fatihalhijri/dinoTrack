<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

test('memberi akses tagihan sesuai role', function (string $ability, Model|string $target, Role $role, bool $allowed) {
    $user = userWithRole($role);

    expect($user->can($ability, $target))->toBe($allowed);
})->with(policyMatrix(Invoice::class, [
    'viewAny' => [Role::Admin, Role::Kasir],
    'view' => [Role::Admin, Role::Kasir],
    'cancel' => [Role::Admin],
    'reissue' => [Role::Admin],
    'resend' => [Role::Admin, Role::Kasir],
]));
