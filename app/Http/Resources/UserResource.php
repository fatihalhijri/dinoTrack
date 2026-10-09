<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Hanya data untuk manajemen user; hash password, rahasia 2FA, dan token tidak dikirim.
 * `can_delete` hanya ada jika `withExists(['receivedPayments', 'activityLogs'])` dimuat:
 * syarat jejak audit yang sama dengan DeleteUser (akun sendiri dan admin terakhir tetap
 * dijaga Action).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = Role::tryFrom((string) $this->getRoleNames()->first());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'is_active' => ! $this->isDeactivated(),
            'deactivated_at' => $this->deactivated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'can_delete' => $this->when(
                $this->hasAttribute('received_payments_exists') && $this->hasAttribute('activity_logs_exists'),
                fn (): bool => ! $this->getAttribute('received_payments_exists') && ! $this->getAttribute('activity_logs_exists'),
            ),
        ];
    }
}
