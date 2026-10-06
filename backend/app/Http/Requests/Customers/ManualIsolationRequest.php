<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Actions\Network\IsolateCustomerManually;
use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isolir dan buka isolir manual oleh admin; keduanya wajib beralasan (K8).
 */
class ManualIsolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer && (bool) $this->user()?->can('isolate', $customer);
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:'.IsolateCustomerManually::MIN_REASON_LENGTH, 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => 'alasan',
        ];
    }
}
