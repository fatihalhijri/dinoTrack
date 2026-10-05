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
