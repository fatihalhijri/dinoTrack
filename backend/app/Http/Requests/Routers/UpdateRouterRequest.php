<?php

declare(strict_types=1);

namespace App\Http\Requests\Routers;

use App\Models\Router;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRouterRequest extends FormRequest
{
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
            ...StoreRouterRequest::routerRules(),
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return StoreRouterRequest::routerAttributes();
    }
}
