<?php

declare(strict_types=1);

namespace App\Http\Requests\Packages;

use App\Models\Package;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('package');

        return $package instanceof Package && (bool) $this->user()?->can('update', $package);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            ...StorePackageRequest::packageRules(),
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return StorePackageRequest::packageAttributes();
    }
}
