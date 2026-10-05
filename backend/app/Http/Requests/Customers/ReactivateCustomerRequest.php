<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Concerns\CustomerValidationRules;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;

class ReactivateCustomerRequest extends FormRequest
{
    use CustomerValidationRules;

    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && (bool) $this->user()?->can('reactivate', $customer);
    }

    /**
     * @return array<string, array<int, Exists|string>>
     */
    public function rules(): array
    {
        return [
            'package_id' => $this->packageIdRules(),
            'billing_day' => $this->billingDayRules(),
        ];
    }

    /**
     * Nilai siap pakai untuk ReactivateCustomer; input form selalu berupa string.
     */
    public function packageId(): int
    {
        return $this->integer('package_id');
    }

    public function billingDay(): int
    {
        return $this->integer('billing_day');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->customerAttributes();
    }
}
