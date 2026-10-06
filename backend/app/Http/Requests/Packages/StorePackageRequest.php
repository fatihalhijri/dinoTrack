<?php

declare(strict_types=1);

namespace App\Http\Requests\Packages;

use App\Concerns\PackageValidationRules;
use App\Models\Package;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    use PackageValidationRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Package::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return $this->packageRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->packageAttributes();
    }
}
