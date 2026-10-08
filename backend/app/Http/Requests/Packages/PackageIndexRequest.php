<?php

declare(strict_types=1);

namespace App\Http\Requests\Packages;

use App\Concerns\IndexQueryRules;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;

class PackageIndexRequest extends FormRequest
{
    use IndexQueryRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Package::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->indexRules(),
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{search: string|null, is_active: bool|null}
     */
    public function filters(): array
    {
        return [
            'search' => $this->searchTerm(),
            'is_active' => $this->filled('is_active') ? $this->boolean('is_active') : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->indexAttributes(),
            'is_active' => 'status aktif',
        ];
    }
}
