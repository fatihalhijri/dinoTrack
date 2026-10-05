<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Support\SettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('memakai nilai default docs/04 jika pengaturan belum ada di database', function () {
    $settings = app(SettingsRepository::class);

    expect($settings->dueDays())->toBe(7)
        ->and($settings->graceDays())->toBe(3)
        ->and($settings->reminderDaysBefore())->toBe(3)
        ->and($settings->prorateFirstMonth())->toBeTrue()
        ->and($settings->autoIsolate())->toBeTrue()
        ->and($settings->autoActivate())->toBeTrue();
});

it('membaca nilai yang sudah diubah admin', function () {
    Setting::query()->create(['key' => 'billing.grace_days', 'value' => 0]);
    Setting::query()->create(['key' => 'billing.prorate_first_month', 'value' => false]);

    $settings = app(SettingsRepository::class);

    expect($settings->graceDays())->toBe(0)
        ->and($settings->prorateFirstMonth())->toBeFalse()
        ->and($settings->dueDays())->toBe(7);
});

it('langsung memakai nilai baru setelah pengaturan diubah meskipun sudah ter-cache', function () {
    $setting = Setting::query()->create(['key' => 'billing.due_days', 'value' => 7]);
    $settings = app(SettingsRepository::class);
    expect($settings->dueDays())->toBe(7);

    $setting->update(['value' => 14]);
    expect($settings->dueDays())->toBe(14);

    $setting->delete();
    expect($settings->dueDays())->toBe(7);
});

it('membaca pengaturan sekali per request lalu memakai nilai yang tersimpan', function () {
    app(SettingsRepository::class)->dueDays();

    DB::enableQueryLog();
    Cache::spy();
    app(SettingsRepository::class)->graceDays();
    app(SettingsRepository::class)->prorateFirstMonth();

    expect(DB::getQueryLog())->toBe([]);
    Cache::shouldNotHaveReceived('rememberForever');
});
