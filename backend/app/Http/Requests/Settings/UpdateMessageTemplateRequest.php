<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageTemplateRequest extends FormRequest
{
    public const int MAX_BODY_LENGTH = 2000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can(Permission::SettingsManage->value);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.self::MAX_BODY_LENGTH],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{body: string, is_active: bool}
     */
    public function template(): array
    {
        return [
            'body' => $this->string('body')->toString(),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'isi pesan',
            'is_active' => 'status aktif',
        ];
    }
}
