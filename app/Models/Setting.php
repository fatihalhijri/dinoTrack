<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SettingsRepository;
use Carbon\CarbonImmutable;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    /**
     * Perubahan pengaturan langsung berlaku karena cache SettingsRepository ikut dibersihkan.
     */
    protected static function booted(): void
    {
        static::saved(fn () => app(SettingsRepository::class)->flush());
        static::deleted(fn () => app(SettingsRepository::class)->flush());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
