<?php

declare(strict_types=1);

namespace App\Http\Requests\Customers;

use App\Concerns\IndexQueryRules;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerIndexRequest extends FormRequest
{
    use IndexQueryRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Customer::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->indexRules(),
            'status' => ['nullable', Rule::enum(CustomerStatus::class)],
            'package_id' => ['nullable', 'integer'],
            'router_id' => ['nullable', 'integer'],
            'network_error' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{search: string|null, status: CustomerStatus|null, package_id: int|null, router_id: int|null, network_error: bool}
     */
    public function filters(): array
    {
        return [
            'search' => $this->searchTerm(),
            'status' => $this->enum('status', CustomerStatus::class),
            'package_id' => $this->filled('package_id') ? $this->integer('package_id') : null,
            'router_id' => $this->filled('router_id') ? $this->integer('router_id') : null,
            'network_error' => $this->boolean('network_error'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->indexAttributes(),
            'status' => 'status',
            'package_id' => 'paket',
            'router_id' => 'router',
            'network_error' => 'galat router',
        ];
    }
}
