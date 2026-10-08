<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoices;

use App\Concerns\IndexQueryRules;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceIndexRequest extends FormRequest
{
    use IndexQueryRules;

    /** Nilai `due` untuk "jatuh tempo minggu ini" (hari ini sampai H+6). */
    public const string DUE_THIS_WEEK = 'this_week';

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Invoice::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->indexRules(),
            'status' => ['nullable', Rule::enum(InvoiceStatus::class)],
            'period' => ['nullable', 'date_format:Y-m'],
            'customer_id' => ['nullable', 'integer'],
            'due' => ['nullable', Rule::in([self::DUE_THIS_WEEK])],
        ];
    }

    /**
     * @return array{search: string|null, status: InvoiceStatus|null, period: string|null, customer_id: int|null, due_soon: bool}
     */
    public function filters(): array
    {
        return [
            'search' => $this->searchTerm(),
            'status' => $this->enum('status', InvoiceStatus::class),
            'period' => $this->filled('period') ? $this->string('period')->toString() : null,
            'customer_id' => $this->filled('customer_id') ? $this->integer('customer_id') : null,
            'due_soon' => $this->input('due') === self::DUE_THIS_WEEK,
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
            'period' => 'periode',
            'customer_id' => 'pelanggan',
            'due' => 'jatuh tempo',
        ];
    }
}
