<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Halaman publik kembali menampilkan nama usaha saja. File baru dihapus setelah commit.
 */
final class RemoveBusinessLogo
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(User $by): void
    {
        DB::transaction(function () use ($by): void {
            $setting = Setting::query()->where('key', SettingsRepository::BUSINESS_LOGO_KEY)->lockForUpdate()->first();

            if ($setting === null) {
                return;
            }

            $previous = is_string($setting->value) ? $setting->value : null;
            $setting->delete();

            $this->logger->log('settings.updated', null, $by, [
                'changes' => [SettingsRepository::BUSINESS_LOGO_KEY => [$previous, null]],
            ]);

            if ($previous !== null) {
                DB::afterCommit(fn (): bool => Storage::disk(SettingsRepository::BUSINESS_LOGO_DISK)->delete($previous));
            }
        });
    }
}
