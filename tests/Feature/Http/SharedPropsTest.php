<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('membagikan data user login dengan kolom yang diizinkan saja', function () {
    $admin = userWithRole(Role::Admin);
    $admin->forceFill(['email_verified_at' => '2026-10-01 08:00:00'])->save();

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->where('auth.user', [
        'id' => $admin->id,
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'admin',
        'role_label' => 'Admin',
        'email_verified_at' => '2026-10-01T08:00:00+07:00',
        'two_factor_enabled' => false,
    ]));
});

it('menandai 2FA aktif hanya setelah dikonfirmasi', function () {
    $user = userWithRole(Role::Kasir);
    $user->forceFill([
        'two_factor_secret' => encrypt('rahasia'),
        'two_factor_recovery_codes' => encrypt('[]'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->where('auth.user.two_factor_enabled', true));
});

it('mengirim role null untuk user tanpa role', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.role', null)
        ->where('auth.user.role_label', null));
});

it('mengirim user null untuk tamu', function () {
    $response = $this->get(route('login'));

    $response->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

it('membagikan nama aplikasi dari konfigurasi', function () {
    config(['app.name' => 'DinoTrack Uji']);

    $response = $this->actingAs(userWithRole(Role::Teknisi))->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->where('name', 'DinoTrack Uji'));
});
