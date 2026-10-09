<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\SettingsRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan profil usaha untuk diubah', function () {
    config(['app.name' => 'Billing ISP']);

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('settings.business.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/business', true)
            ->where('business', ['name' => null, 'address' => null, 'whatsapp' => null])
            ->where('default_name', 'Billing ISP')
            ->where('logo_url', null)
            ->where('logo_max_kilobytes', 1024));
});

it('menyimpan profil usaha dengan nomor WA yang dinormalisasi', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('settings.business.update'), ['name' => 'Dino Net', 'address' => 'Jl. Melati 1', 'whatsapp' => '0812-3456-7890'])
        ->assertRedirect(route('settings.business.edit'));

    expect(app(SettingsRepository::class)->businessWhatsapp())->toBe('6281234567890');
});

it('menolak nomor WA admin yang tidak valid', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('settings.business.update'), ['whatsapp' => '12345'])
        ->assertSessionHasErrors(['whatsapp' => 'Nomor WhatsApp harus berformat 62xxxxxxxxxx, contoh 6281234567890.']);
});

it('menyimpan logo usaha di disk publik dan menampilkannya di halaman pengaturan', function () {
    Storage::fake('public');
    $admin = userWithRole(Role::Admin);

    $this->actingAs($admin)
        ->post(route('settings.business.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png', 300, 100)])
        ->assertRedirect(route('settings.business.edit'));

    $path = Setting::query()->where('key', SettingsRepository::BUSINESS_LOGO_KEY)->sole()->value;
    Storage::disk('public')->assertExists($path);
    expect($path)->toStartWith('business/')
        ->and(ActivityLog::query()->where('action', 'settings.updated')->sole())
        ->user_id->toBe($admin->id)
        ->properties->toBe(['changes' => [SettingsRepository::BUSINESS_LOGO_KEY => [null, $path]]]);

    $this->actingAs($admin)
        ->get(route('settings.business.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('logo_url', Storage::disk('public')->url($path)));
});

it('menghapus file logo lama saat logo diganti', function () {
    Storage::fake('public');
    $admin = userWithRole(Role::Admin);

    $this->actingAs($admin)->post(route('settings.business.logo.store'), ['logo' => UploadedFile::fake()->image('lama.png')]);
    $oldPath = app(SettingsRepository::class)->get(SettingsRepository::BUSINESS_LOGO_KEY);

    $this->actingAs($admin)->post(route('settings.business.logo.store'), ['logo' => UploadedFile::fake()->image('baru.webp')]);
    $newPath = app(SettingsRepository::class)->get(SettingsRepository::BUSINESS_LOGO_KEY);

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

it('menolak logo yang bukan gambar PNG, JPG, atau WebP', function (UploadedFile $file) {
    Storage::fake('public');

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('settings.business.logo.store'), ['logo' => $file])
        ->assertSessionHasErrors(['logo' => 'Logo harus berupa gambar PNG, JPG, atau WebP.']);

    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(Setting::query()->where('key', SettingsRepository::BUSINESS_LOGO_KEY)->exists())->toBeFalse();
})->with([
    'PDF' => fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
    'SVG (bisa memuat skrip)' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'GIF' => fn () => UploadedFile::fake()->image('logo.gif'),
]);

it('menolak logo lebih dari 1 MB', function () {
    Storage::fake('public');

    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('settings.business.logo.store'), ['logo' => UploadedFile::fake()->image('logo.jpg')->size(1025)])
        ->assertSessionHasErrors(['logo' => 'Ukuran logo maksimal 1 MB.']);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('menghapus logo usaha beserta filenya', function () {
    Storage::fake('public');
    $admin = userWithRole(Role::Admin);
    $this->actingAs($admin)->post(route('settings.business.logo.store'), ['logo' => UploadedFile::fake()->image('logo.png')]);
    $path = app(SettingsRepository::class)->get(SettingsRepository::BUSINESS_LOGO_KEY);

    $this->actingAs($admin)
        ->delete(route('settings.business.logo.destroy'))
        ->assertRedirect(route('settings.business.edit'));

    Storage::disk('public')->assertMissing($path);
    expect(app(SettingsRepository::class)->businessLogoUrl())->toBeNull()
        ->and(ActivityLog::query()->where('action', 'settings.updated')->latest('id')->first()?->properties)
        ->toBe(['changes' => [SettingsRepository::BUSINESS_LOGO_KEY => [$path, null]]]);
});

it('tidak mencatat apa pun saat menghapus logo yang belum ada', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->delete(route('settings.business.logo.destroy'))
        ->assertRedirect(route('settings.business.edit'));

    expect(ActivityLog::query()->count())->toBe(0);
});
