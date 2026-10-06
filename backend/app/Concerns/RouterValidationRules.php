<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aturan bersama Form Request router. Dipakai oleh kelas turunan FormRequest.
 */
trait RouterValidationRules
{
    /**
     * Aturan tanpa password, karena wajib atau tidaknya berbeda antara tambah dan ubah.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function routerRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-:]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:100'],
            'use_ssl' => ['required', 'boolean'],
            'isolation_profile' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Nilai bertipe untuk CreateRouter/UpdateRouter; input form selalu berupa string (M12).
     * Password kosong berarti null (UpdateRouter mempertahankan password lama).
     *
     * @return array{name: string, host: string, port: int, username: string, password: string|null, use_ssl: bool, isolation_profile: string, is_active: bool}
     */
    protected function routerFields(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'host' => $this->string('host')->toString(),
            'port' => $this->integer('port'),
            'username' => $this->string('username')->toString(),
            'password' => $this->filled('password') ? $this->string('password')->toString() : null,
            'use_ssl' => $this->boolean('use_ssl'),
            'isolation_profile' => $this->string('isolation_profile')->toString(),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function routerAttributes(): array
    {
        return [
            'name' => 'nama router',
            'host' => 'host',
            'port' => 'port API',
            'username' => 'username',
            'password' => 'password',
            'use_ssl' => 'SSL',
            'isolation_profile' => 'profil isolir',
            'is_active' => 'status aktif',
        ];
    }
}
