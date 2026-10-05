<?php

declare(strict_types=1);

namespace App\Http\Requests\Packages;

use App\Models\Package;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Package::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return self::packageRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::packageAttributes();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public static function packageRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'speed_label' => ['required', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:1', 'max:100000000'],
            'mikrotik_profile' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function packageAttributes(): array
    {
        return [
            'name' => 'nama paket',
            'speed_label' => 'kecepatan',
            'price' => 'harga',
            'mikrotik_profile' => 'profil Mikrotik',
            'description' => 'deskripsi',
            'is_active' => 'status aktif',
        ];
    }
}
