<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadBusinessLogoRequest extends FormRequest
{
    public const int MAX_KILOBYTES = 1024;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::SettingsManage->value);
    }

    /**
     * Tipe dibaca dari isi file, bukan ekstensi. SVG ditolak karena bisa memuat skrip.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    public function logo(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('logo');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.image' => 'Logo harus berupa gambar PNG, JPG, atau WebP.',
            'logo.mimes' => 'Logo harus berupa gambar PNG, JPG, atau WebP.',
            'logo.max' => 'Ukuran logo maksimal 1 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo' => 'logo usaha',
        ];
    }
}
