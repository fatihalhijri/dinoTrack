<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Nilai default aturan tagihan (docs/04-aturan-bisnis.md). Nilai yang sudah diubah admin tidak ditimpa.
 */
class SettingSeeder extends Seeder
{
    /** Sumber tunggalnya di SettingsRepository agar default saat membaca dan saat seeding sama. */
    public const array DEFAULTS = SettingsRepository::DEFAULTS;

    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
