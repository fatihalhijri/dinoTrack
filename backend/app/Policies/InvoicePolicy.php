<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

/**
 * Invoice dibuat oleh sistem, sehingga tidak ada create/update/delete.
 * Larangan membatalkan invoice `paid`/`cancelled` dijaga di Action.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo(Permission::InvoicesView);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->checkPermissionTo(Permission::InvoicesView);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->checkPermissionTo(Permission::InvoicesCancel);
    }

    /**
     * Menerbitkan ulang periode yang invoice-nya dibatalkan. Memakai permission yang sama dengan
     * pembatalan agar matriks permission yang disetujui tidak berubah.
     */
    public function reissue(User $user, Invoice $invoice): bool
    {
        return $user->checkPermissionTo(Permission::InvoicesCancel);
    }

    /**
     * Mengirim ulang tagihan ke WhatsApp pelanggan.
     */
    public function resend(User $user, Invoice $invoice): bool
    {
        return $user->checkPermissionTo(Permission::InvoicesResend);
    }
}
