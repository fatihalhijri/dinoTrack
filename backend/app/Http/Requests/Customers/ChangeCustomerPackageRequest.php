<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class ChangeCustomerPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && (bool) $this->user()?->can('update', $customer);
    }

    /**
     * `package_id` kosong berarti membatalkan rencana ganti paket.
     *
     * @return array<string, array<int, Exists|string>>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['present', 'nullable', 'integer', Rule::exists(Package::class, 'id')],
        ];
    }

    /**
     * Nilai siap pakai untuk ChangeCustomerPackage; input form selalu berupa string.
     */
    public function packageId(): ?int
    {
        return $this->filled('package_id') ? $this->integer('package_id') : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'package_id' => 'paket',
        ];
    }
}
