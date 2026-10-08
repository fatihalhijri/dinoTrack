<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission as PermissionModel;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : $this->userData($user),
                'permissions' => fn (): array => $this->permissionsOf($user),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Data user minimal untuk semua halaman; model mentah tidak dikirim agar kolom baru
     * atau relasi yang kebetulan termuat (misalnya roles) tidak ikut terkirim (audit R-3).
     *
     * @return array{id: int, name: string, email: string, role: string|null, role_label: string|null, email_verified_at: string|null, two_factor_enabled: bool}
     */
    private function userData(User $user): array
    {
        $role = Role::tryFrom((string) $user->getRoleNames()->first());

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'two_factor_enabled' => $user->hasEnabledTwoFactorAuthentication(),
        ];
    }

    /**
     * Daftar permission untuk menyembunyikan tombol di frontend; otorisasi tetap di backend.
     * Admin selalu mendapat semua permission, sejalan dengan Gate::before.
     *
     * @return list<string>
     */
    private function permissionsOf(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->hasRole(Role::Admin)) {
            return Permission::values();
        }

        return array_values($user->getAllPermissions()
            ->map(fn (PermissionModel $permission): string => $permission->name)
            ->sort()
            ->all());
    }
}
