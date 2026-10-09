<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\SettingsRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Logo usaha untuk halaman publik pelanggan. File disimpan dengan nama acak di disk publik
 * (setiap unggahan mendapat URL baru, sehingga browser tidak memakai logo lama dari cache).
 * Logo lama baru dihapus setelah commit, dan file baru dihapus lagi jika transaksi gagal.
 */
final class UploadBusinessLogo
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(UploadedFile $file, User $by): string
    {
        $disk = Storage::disk(SettingsRepository::BUSINESS_LOGO_DISK);
        $path = $disk->putFile(SettingsRepository::BUSINESS_LOGO_DIRECTORY, $file);

        if ($path === false) {
            throw new RuntimeException('Logo usaha gagal disimpan ke disk.');
        }

        try {
            DB::transaction(function () use ($path, $by, $disk): void {
                $setting = Setting::query()->where('key', SettingsRepository::BUSINESS_LOGO_KEY)->lockForUpdate()->first();
                $previous = is_string($setting?->value) ? $setting->value : null;

                Setting::query()->updateOrCreate(['key' => SettingsRepository::BUSINESS_LOGO_KEY], ['value' => $path]);

                $this->logger->log('settings.updated', null, $by, [
                    'changes' => [SettingsRepository::BUSINESS_LOGO_KEY => [$previous, $path]],
                ]);

                if ($previous !== null) {
                    DB::afterCommit(fn (): bool => $disk->delete($previous));
                }
            });
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        return $path;
    }
}
