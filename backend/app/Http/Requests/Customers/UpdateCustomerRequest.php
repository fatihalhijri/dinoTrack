<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Concerns\CustomerValidationRules;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Pembatasan perubahan router, username, dan tanggal tagih untuk pelanggan terpasang
 * dijaga di UpdateCustomer, bukan di sini.
 */
class UpdateCustomerRequest extends FormRequest
{
    use CustomerValidationRules;

    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && (bool) $this->user()?->can('update', $customer);
    }

    /**
     * @return array<string, array<int, Exists|Unique|string>>
     */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            ...$this->customerRules($customer->id),
            'billing_day' => ['sometimes', ...$this->billingDayRules()],
        ];
    }

    /**
     * Tanggal tagih hanya ikut jika dikirim (boleh diubah selama pelanggan `pending`).
     *
     * @return array{name: string, phone: string, address: string, odp: string|null, latitude: string|null, longitude: string|null, router_id: int, pppoe_username: string, notes: string|null, billing_day?: int}
     */
    public function customerData(): array
    {
        $data = $this->customerFields();

        if ($this->filled('billing_day')) {
            $data['billing_day'] = $this->integer('billing_day');
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->customerMessages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->customerAttributes();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();
    }
}
