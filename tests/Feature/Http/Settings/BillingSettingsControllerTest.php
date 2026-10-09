<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\SettingsRepository;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan aturan tagihan saat ini', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('settings.billing.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/billing', true)
            ->where('billing.due_days', 7)
            ->where('billing.grace_days', 3)
            ->where('billing.auto_isolate', true)
            ->where('limits', ['max_due_days' => 31, 'max_grace_days' => 30]));
});

it('menyimpan aturan tagihan dari input form', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('settings.billing.update'), [
            'due_days' => '10',
            'grace_days' => '0',
            'reminder_days_before' => '2',
            'prorate_first_month' => '0',
            'auto_isolate' => '1',
            'auto_activate' => '1',
        ])
        ->assertRedirect(route('settings.billing.edit'));

    $settings = app(SettingsRepository::class);
    expect($settings->dueDays())->toBe(10)
        ->and($settings->graceDays())->toBe(0)
        ->and($settings->prorateFirstMonth())->toBeFalse();
});

it('memvalidasi batas aturan tagihan', function (array $input, string $field, string $message) {
    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('settings.billing.update'), [
            'due_days' => 7,
            'grace_days' => 3,
            'reminder_days_before' => 3,
            'prorate_first_month' => true,
            'auto_isolate' => true,
            'auto_activate' => true,
            ...$input,
        ])
        ->assertSessionHasErrors([$field => $message]);
})->with([
    'jatuh tempo 0 hari' => [['due_days' => 0], 'due_days', 'Jatuh tempo harus bernilai antara 1 sampai 31.'],
    'toleransi lebih dari 30 hari' => [['grace_days' => 31], 'grace_days', 'Masa toleransi harus bernilai antara 0 sampai 30.'],
    'pengingat sama dengan jatuh tempo' => [['due_days' => 3, 'reminder_days_before' => 3], 'reminder_days_before', 'Pengingat harus kurang dari jumlah hari jatuh tempo.'],
]);
