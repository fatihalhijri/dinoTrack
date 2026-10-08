<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Aturan tagihan di docs/04 yang bisa diubah admin. Berlaku untuk proses berikutnya: invoice
 * yang sudah terbit tidak dihitung ulang.
 */
final class UpdateBillingSettings
{
    public const int MAX_DUE_DAYS = 31;

    public const int MAX_GRACE_DAYS = 30;

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{due_days: int, grace_days: int, reminder_days_before: int, prorate_first_month: bool, auto_isolate: bool, auto_activate: bool}  $values
     */
    public function handle(array $values, User $by): void
    {
        // Pengingat H-N pada atau sebelum hari terbit tidak pernah terkirim tepat waktu.
        if ($values['reminder_days_before'] >= $values['due_days']) {
            throw ValidationException::withMessages([
                'reminder_days_before' => 'Pengingat harus kurang dari jumlah hari jatuh tempo.',
            ]);
        }

        DB::transaction(function () use ($values, $by): void {
            $changes = [];

            foreach (array_keys(SettingsRepository::DEFAULTS) as $key) {
                $field = substr($key, strlen('billing.'));
                $current = $this->settings->get($key);
                $new = $values[$field];

                if ($current === $new) {
                    continue;
                }

                $changes[$key] = [$current, $new];
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $new]);
            }

            if ($changes !== []) {
                $this->logger->log('settings.updated', null, $by, ['changes' => $changes]);
            }
        });
    }
}
