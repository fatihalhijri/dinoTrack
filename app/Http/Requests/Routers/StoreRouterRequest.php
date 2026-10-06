<?php

declare(strict_types=1);

namespace App\Http\Requests\Routers;

use App\Concerns\RouterValidationRules;
use App\Models\Router;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRouterRequest extends FormRequest
{
    use RouterValidationRules;

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
            ...$this->routerRules(),
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{name: string, host: string, port: int, username: string, password: string, use_ssl: bool, isolation_profile: string, is_active: bool}
     */
    public function routerData(): array
    {
        return [...$this->routerFields(), 'password' => $this->string('password')->toString()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->routerAttributes();
    }
}
