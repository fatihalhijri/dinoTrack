<?php

declare(strict_types=1);

namespace App\Http\Requests\Routers;

use App\Concerns\RouterValidationRules;
use App\Models\Router;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRouterRequest extends FormRequest
{
    use RouterValidationRules;

    public function authorize(): bool
    {
        $router = $this->route('router');

        return $router instanceof Router && (bool) $this->user()?->can('update', $router);
    }

    /**
     * Password kosong berarti password lama dipertahankan.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->routerRules(),
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{name: string, host: string, port: int, username: string, password: string|null, use_ssl: bool, isolation_profile: string, is_active: bool}
     */
    public function routerData(): array
    {
        return $this->routerFields();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->routerAttributes();
    }
}
