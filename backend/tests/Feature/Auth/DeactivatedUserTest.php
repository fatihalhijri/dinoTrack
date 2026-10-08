<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;

it('menolak login akun nonaktif dengan pesan khusus setelah password benar', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => EnsureUserIsActive::MESSAGE]);

    $this->assertGuest();
});

it('tidak membocorkan status akun nonaktif jika password salah', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'salah'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
});

it('mengeluarkan pegawai yang dinonaktifkan dari session yang masih berjalan', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['deactivated_at' => now()])->save();

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', EnsureUserIsActive::MESSAGE);
    $this->assertGuest();
});
