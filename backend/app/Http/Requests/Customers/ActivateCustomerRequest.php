<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Menandai pelanggan terpasang. Batas bawah tanggal pasang (tanggal pendaftaran) dijaga
 * di ActivateNewCustomer.
 */
class ActivateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && (bool) $this->user()?->can('activate', $customer);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'installed_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * Tanggal pasang siap pakai untuk ActivateNewCustomer; kosong berarti hari ini.
     */
    public function installedAt(): CarbonImmutable
    {
        return $this->filled('installed_at')
            ? CarbonImmutable::parse($this->string('installed_at')->toString())->startOfDay()
            : today();
    }

    /**
     * Pesan bawaan before_or_equal menampilkan parameter mentah ("today").
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'installed_at.before_or_equal' => 'Tanggal pasang tidak boleh di masa depan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'installed_at' => 'tanggal pasang',
        ];
    }
}
