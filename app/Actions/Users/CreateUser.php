<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Akun pegawai dibuat admin dengan password awal (tanpa registrasi publik). Email langsung
 * dianggap terverifikasi karena pemiliknya dikenal admin dan pengiriman email belum tentu
 * dikonfigurasi di instalasi ISP.
 */
final class CreateUser
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function handle(array $attributes, Role $role, User $by): User
    {
        return DB::transaction(function () use ($attributes, $role, $by): User {
            $user = new User([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $attributes['password'],
            ]);
            $user->email_verified_at = now();
            $user->save();
            $user->assignRole($role);

            $this->logger->log('user.created', $user, $by, [
                'email' => $user->email,
                'role' => $role->value,
            ]);

            return $user;
        });
    }
}
