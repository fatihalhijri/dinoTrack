<?php

declare(strict_types=1);

namespace App\Http\Requests\Routers;

use App\Models\Router;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Router::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            ...self::routerRules(),
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::routerAttributes();
    }

    /**
     * Aturan tanpa password, karena wajib atau tidaknya berbeda antara tambah dan ubah.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public static function routerRules(): array
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
     * @return array<string, string>
     */
    public static function routerAttributes(): array
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
