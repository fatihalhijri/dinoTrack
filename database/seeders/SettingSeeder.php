<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Nilai default aturan tagihan (docs/04-aturan-bisnis.md). Nilai yang sudah diubah admin tidak ditimpa.
 */
class SettingSeeder extends Seeder
{
    /** @var array<string, int|bool> */
    public const array DEFAULTS = [
        'billing.due_days' => 7,
        'billing.grace_days' => 3,
        'billing.reminder_days_before' => 3,
        'billing.prorate_first_month' => true,
        'billing.auto_isolate' => true,
        'billing.auto_activate' => true,
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
