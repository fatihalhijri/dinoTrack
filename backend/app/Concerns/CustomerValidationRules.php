<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Package;
use App\Models\Router;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Aturan bersama Form Request pelanggan. Dipakai oleh kelas turunan FormRequest.
 */
trait CustomerValidationRules
{
    /**
     * @return array<string, array<int, Exists|Unique|string>>
     */
    protected function customerRules(?int $ignoreCustomerId = null): array
    {
        $uniqueUsername = Rule::unique('customers', 'pppoe_username')
            ->where('router_id', $this->integer('router_id'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^62\d{8,13}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'odp' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'router_id' => ['required', 'integer', Rule::exists(Router::class, 'id')],
            // Username dari pelanggan yang di-soft-delete tetap dihitung karena unique index juga mencakupnya.
            'pppoe_username' => [
                'required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._@-]+$/',
                $ignoreCustomerId === null ? $uniqueUsername : $uniqueUsername->ignore($ignoreCustomerId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, Exists|string>
     */
    protected function packageIdRules(): array
    {
        return ['required', 'integer', Rule::exists(Package::class, 'id')];
    }

    /**
     * Tanggal 29–31 diterima lalu dibulatkan ke 28 oleh Action (docs/04).
     *
     * @return array<int, string>
     */
    protected function billingDayRules(): array
    {
        return ['required', 'integer', 'between:1,31'];
    }

    protected function normalizePhoneInput(): void
    {
        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function customerMessages(): array
    {
        return [
            'phone.regex' => 'Nomor WhatsApp harus berformat 62xxxxxxxxxx, contoh 6281234567890.',
            'pppoe_username.regex' => 'Username PPPoE hanya boleh berisi huruf, angka, titik, garis bawah, tanda hubung, dan @.',
            'pppoe_username.unique' => 'Username PPPoE sudah dipakai pelanggan lain di router ini.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function customerAttributes(): array
    {
        return [
            'name' => 'nama',
            'phone' => 'nomor WhatsApp',
            'address' => 'alamat',
            'odp' => 'ODP',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'router_id' => 'router',
            'pppoe_username' => 'username PPPoE',
            'notes' => 'catatan',
            'package_id' => 'paket',
            'billing_day' => 'tanggal tagih',
        ];
    }
}
