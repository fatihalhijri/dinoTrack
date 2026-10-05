<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

/**
 * Pembayaran QRIS dicatat oleh webhook tanpa user, sehingga `create` hanya untuk pembayaran manual.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permission::PaymentsView);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->checkPermissionTo(Permission::PaymentsView);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo(Permission::PaymentsRecord);
    }

    /**
     * Menandai pembayaran anomali (`needs_review`) sebagai `resolved`.
     */
    public function review(User $user, Payment $payment): bool
    {
        return $user->checkPermissionTo(Permission::PaymentsReview);
    }
}
