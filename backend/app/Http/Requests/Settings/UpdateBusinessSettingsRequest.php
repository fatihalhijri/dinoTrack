<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::SettingsManage->value);
    }

    /**
     * Semua opsional: nama kosong kembali ke APP_NAME, WA kosong menyembunyikan tombol kontak.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'whatsapp' => ['nullable', 'string', 'regex:/^62\d{8,13}$/'],
        ];
    }

    /**
     * @return array{name: string|null, address: string|null, whatsapp: string|null}
     */
    public function profile(): array
    {
        return [
            'name' => $this->filled('name') ? $this->string('name')->trim()->toString() : null,
            'address' => $this->filled('address') ? $this->string('address')->trim()->toString() : null,
            'whatsapp' => $this->filled('whatsapp') ? $this->string('whatsapp')->toString() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'Nomor WhatsApp harus berformat 62xxxxxxxxxx, contoh 6281234567890.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama usaha',
            'address' => 'alamat usaha',
            'whatsapp' => 'nomor WhatsApp admin',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('whatsapp'))) {
            $this->merge(['whatsapp' => PhoneNumber::normalize($this->input('whatsapp'))]);
        }
    }
}
