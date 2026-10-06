<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aturan bersama Form Request paket. Dipakai oleh kelas turunan FormRequest.
 */
trait PackageValidationRules
{
    /**
     * Status aktif tidak termasuk: diubah lewat ActivatePackage/DeactivatePackage.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function packageRules(): array
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
     * Nilai bertipe untuk CreatePackage/UpdatePackage; input form selalu berupa string (M12).
     *
     * @return array{name: string, speed_label: string, price: int, mikrotik_profile: string, description: string|null}
     */
    public function packageData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'speed_label' => $this->string('speed_label')->toString(),
            'price' => $this->integer('price'),
            'mikrotik_profile' => $this->string('mikrotik_profile')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function packageAttributes(): array
    {
        return [
            'name' => 'nama paket',
            'speed_label' => 'kecepatan',
            'price' => 'harga',
            'mikrotik_profile' => 'profil Mikrotik',
            'description' => 'deskripsi',
        ];
    }
}
