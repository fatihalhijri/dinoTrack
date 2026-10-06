<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\DB;

/**
 * Profil usaha untuk halaman publik dan pesan pelanggan. Nilai kosong menghapus baris
 * pengaturan (kolom `value` NOT NULL), sehingga nama usaha kembali ke APP_NAME.
 */
final class UpdateBusinessProfile
{
    /** @var array<string, string> field input => key pengaturan */
    public const array KEYS = [
        'name' => 'business.name',
        'address' => 'business.address',
        'whatsapp' => 'business.whatsapp',
    ];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{name?: string|null, address?: string|null, whatsapp?: string|null}  $attributes  nomor WA sudah dinormalisasi 62xxx
     */
    public function handle(array $attributes, User $by): void
    {
        DB::transaction(function () use ($attributes, $by): void {
            $changes = [];

            foreach (self::KEYS as $field => $key) {
                $value = trim((string) ($attributes[$field] ?? ''));
                $current = $this->settings->all()[$key] ?? null;
                $new = $value === '' ? null : $value;

                if ($current === $new) {
                    continue;
                }

                $changes[$key] = [$current, $new];

                if ($new === null) {
                    Setting::query()->where('key', $key)->first()?->delete();
                } else {
                    Setting::query()->updateOrCreate(['key' => $key], ['value' => $new]);
                }
            }

            if ($changes !== []) {
                $this->logger->log('settings.updated', null, $by, ['changes' => $changes]);
            }
        });
    }
}
