<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Concerns\CustomerValidationRules;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

class StoreCustomerRequest extends FormRequest
{
    use CustomerValidationRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Customer::class);
    }

    /**
     * @return array<string, array<int, Exists|Unique|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->customerRules(),
            'package_id' => $this->packageIdRules(),
            'billing_day' => $this->billingDayRules(),
        ];
    }

    /**
     * @return array{name: string, phone: string, address: string, odp: string|null, latitude: string|null, longitude: string|null, router_id: int, pppoe_username: string, notes: string|null, package_id: int, billing_day: int}
     */
    public function customerData(): array
    {
        return [
            ...$this->customerFields(),
            'package_id' => $this->integer('package_id'),
            'billing_day' => $this->integer('billing_day'),
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
