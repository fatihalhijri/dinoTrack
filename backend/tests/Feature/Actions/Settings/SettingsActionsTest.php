<?php

declare(strict_types=1);

use App\Actions\Settings\UpdateBillingSettings;
use App\Actions\Settings\UpdateBusinessProfile;
use App\Actions\Settings\UpdateMessageTemplate;
use App\Enums\MessageTemplateKey;
use App\Models\ActivityLog;
use App\Models\MessageTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingsRepository;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, int|bool>  $overrides
 * @return array{due_days: int, grace_days: int, reminder_days_before: int, prorate_first_month: bool, auto_isolate: bool, auto_activate: bool}
 */
function billingValues(array $overrides = []): array
{
    /** @var array{due_days: int, grace_days: int, reminder_days_before: int, prorate_first_month: bool, auto_isolate: bool, auto_activate: bool} */
    return [
        'due_days' => 7,
        'grace_days' => 3,
        'reminder_days_before' => 3,
        'prorate_first_month' => true,
        'auto_isolate' => true,
        'auto_activate' => true,
        ...$overrides,
    ];
}

it('menyimpan profil usaha dan langsung terbaca di halaman publik', function () {
    $settings = app(SettingsRepository::class);
    $settings->businessName();

    app(UpdateBusinessProfile::class)->handle(['name' => 'Dino Net', 'address' => 'Jl. Melati 1', 'whatsapp' => '6281234567890'], User::factory()->create());

    expect($settings->businessName())->toBe('Dino Net')
        ->and($settings->businessAddress())->toBe('Jl. Melati 1')
        ->and($settings->businessWhatsapp())->toBe('6281234567890');
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'settings.updated']);
});

it('menghapus profil usaha yang dikosongkan sehingga nama kembali ke APP_NAME', function () {
    Setting::query()->create(['key' => 'business.name', 'value' => 'Dino Net']);
    config(['app.name' => 'Billing ISP']);

    app(UpdateBusinessProfile::class)->handle(['name' => '  ', 'address' => null, 'whatsapp' => null], User::factory()->create());

    $this->assertDatabaseMissing(Setting::class, ['key' => 'business.name']);
    expect(app(SettingsRepository::class)->businessName())->toBe('Billing ISP');
});

it('tidak mencatat aktivitas jika profil usaha tidak berubah', function () {
    app(UpdateBusinessProfile::class)->handle(['name' => null, 'address' => null, 'whatsapp' => null], User::factory()->create());

    $this->assertDatabaseMissing(ActivityLog::class, ['action' => 'settings.updated']);
});

it('menyimpan aturan tagihan yang berubah saja dan mencatat nilai lama-baru', function () {
    app(UpdateBillingSettings::class)->handle(billingValues(['grace_days' => 0, 'auto_isolate' => false]), User::factory()->create());

    $settings = app(SettingsRepository::class);
    expect($settings->graceDays())->toBe(0)
        ->and($settings->autoIsolate())->toBeFalse()
        ->and(Setting::query()->pluck('key')->sort()->values()->all())->toBe(['billing.auto_isolate', 'billing.grace_days']);
    expect(ActivityLog::query()->where('action', 'settings.updated')->sole()->properties['changes'])
        ->toEqual(['billing.grace_days' => [3, 0], 'billing.auto_isolate' => [true, false]]);
});

it('menolak pengingat yang tidak kurang dari hari jatuh tempo', function () {
    app(UpdateBillingSettings::class)->handle(billingValues(['due_days' => 3, 'reminder_days_before' => 3]), User::factory()->create());
})->throws(ValidationException::class, 'Pengingat harus kurang dari jumlah hari jatuh tempo.');

it('mengubah isi dan status template pesan', function () {
    $template = MessageTemplate::factory()->create(['key' => MessageTemplateKey::Isolated, 'body' => 'Lama', 'is_active' => true]);

    app(UpdateMessageTemplate::class)->handle($template, ['body' => 'Halo {nama}', 'is_active' => false], User::factory()->create());

    expect($template->refresh())
        ->body->toBe('Halo {nama}')
        ->is_active->toBeFalse();
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'message_template.updated', 'subject_id' => $template->id]);
});
