<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Actions\Settings\UpdateBillingSettings;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Batas pengingat (< hari jatuh tempo) juga dijaga UpdateBillingSettings.
 */
class UpdateBillingSettingsRequest extends FormRequest
{
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
            'due_days' => ['required', 'integer', 'between:1,'.UpdateBillingSettings::MAX_DUE_DAYS],
            'grace_days' => ['required', 'integer', 'between:0,'.UpdateBillingSettings::MAX_GRACE_DAYS],
            'reminder_days_before' => ['required', 'integer', 'min:0', 'lt:due_days'],
            'prorate_first_month' => ['required', 'boolean'],
            'auto_isolate' => ['required', 'boolean'],
            'auto_activate' => ['required', 'boolean'],
        ];
    }

    /**
     * Nilai bertipe untuk UpdateBillingSettings; input form selalu berupa string.
     *
     * @return array{due_days: int, grace_days: int, reminder_days_before: int, prorate_first_month: bool, auto_isolate: bool, auto_activate: bool}
     */
    public function billingSettings(): array
    {
        return [
            'due_days' => $this->integer('due_days'),
            'grace_days' => $this->integer('grace_days'),
            'reminder_days_before' => $this->integer('reminder_days_before'),
            'prorate_first_month' => $this->boolean('prorate_first_month'),
            'auto_isolate' => $this->boolean('auto_isolate'),
            'auto_activate' => $this->boolean('auto_activate'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reminder_days_before.lt' => 'Pengingat harus kurang dari jumlah hari jatuh tempo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'due_days' => 'jatuh tempo',
            'grace_days' => 'masa toleransi',
            'reminder_days_before' => 'pengingat sebelum jatuh tempo',
            'prorate_first_month' => 'prorata bulan pertama',
            'auto_isolate' => 'isolir otomatis',
            'auto_activate' => 'aktivasi otomatis',
        ];
    }
}
