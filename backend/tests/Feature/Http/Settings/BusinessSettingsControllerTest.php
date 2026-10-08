<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\SettingsRepository;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan profil usaha untuk diubah', function () {
    config(['app.name' => 'Billing ISP']);

    $this->actingAs(userWithRole(Role::Admin))
        ->get(route('settings.business.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/business')
            ->where('business', ['name' => null, 'address' => null, 'whatsapp' => null])
            ->where('default_name', 'Billing ISP'));
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
