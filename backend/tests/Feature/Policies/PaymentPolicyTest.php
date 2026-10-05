<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

test('memberi akses pembayaran sesuai role', function (string $ability, Model|string $target, Role $role, bool $allowed) {
    $user = userWithRole($role);

    expect($user->can($ability, $target))->toBe($allowed);
})->with(policyMatrix(Payment::class, [
    'viewAny' => [Role::Admin, Role::Kasir],
    'view' => [Role::Admin, Role::Kasir],
    'create' => [Role::Admin, Role::Kasir],
    'review' => [Role::Admin],
]));
