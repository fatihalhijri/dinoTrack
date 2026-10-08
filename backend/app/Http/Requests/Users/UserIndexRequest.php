<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Concerns\IndexQueryRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    use IndexQueryRules;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->indexRules(),
            'role' => ['nullable', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array{search: string|null, role: Role|null}
     */
    public function filters(): array
    {
        return [
            'search' => $this->searchTerm(),
            'role' => $this->enum('role', Role::class),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...$this->indexAttributes(),
            'role' => 'role',
        ];
    }
}
