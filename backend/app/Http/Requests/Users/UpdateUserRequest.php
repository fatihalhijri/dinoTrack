<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Larangan mengubah role sendiri dan menurunkan admin terakhir dijaga di UpdateUser.
 */
class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && (bool) $this->user()?->can('update', $user);
    }

    /**
     * Password kosong berarti password lama dipertahankan.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            ...$this->profileRules($user->id),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }

    /**
     * @return array{name: string, email: string, password: string|null}
     */
    public function userAttributes(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'password' => $this->filled('password') ? $this->string('password')->toString() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'password' => 'password',
            'role' => 'role',
        ];
    }
}
