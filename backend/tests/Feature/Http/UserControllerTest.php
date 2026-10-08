<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('menampilkan daftar user tanpa data rahasia akun', function () {
    $admin = userWithRole(Role::Admin);
    User::factory()->withTwoFactor()->create(['name' => 'Kasir Satu'])->assignRole(Role::Kasir);

    $response = $this->actingAs($admin)->get(route('users.index', ['role' => 'kasir']));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 1, fn (Assert $user) => $user
                ->where('name', 'Kasir Satu')
                ->where('role', 'kasir')
                ->where('is_active', true)
                ->missing('password')
                ->missing('two_factor_secret')
                ->missing('two_factor_recovery_codes')
                ->missing('remember_token')
                ->etc())
            ->has('roles', 3));
});

it('admin membuat akun pegawai', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->post(route('users.store'), [
            'name' => 'Teknisi Baru',
            'email' => 'teknisi@isp.test',
            'password' => 'password-kuat',
            'password_confirmation' => 'password-kuat',
            'role' => 'teknisi',
        ])
        ->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'teknisi@isp.test')->sole();
    expect($user->hasRole(Role::Teknisi))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue();
});

it('memvalidasi email unik dan konfirmasi password saat membuat akun', function () {
    $admin = userWithRole(Role::Admin);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Ganda',
            'email' => $admin->email,
            'password' => 'password-kuat',
            'password_confirmation' => 'beda',
            'role' => 'kasir',
        ])
        ->assertSessionHasErrors(['email', 'password']);
});

it('admin mengubah role pegawai', function () {
    $user = userWithRole(Role::Kasir);

    $this->actingAs(userWithRole(Role::Admin))
        ->put(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'role' => 'teknisi'])
        ->assertRedirect(route('users.index'));

    expect($user->refresh()->getRoleNames()->all())->toBe(['teknisi']);
});

it('admin menonaktifkan dan mengaktifkan kembali pegawai', function () {
    $admin = userWithRole(Role::Admin);
    $user = userWithRole(Role::Kasir);

    $this->actingAs($admin)->post(route('users.deactivate', $user))->assertRedirect();
    expect($user->refresh()->isDeactivated())->toBeTrue();

    $this->actingAs($admin)->post(route('users.reactivate', $user))->assertRedirect();
    expect($user->refresh()->isDeactivated())->toBeFalse();
});

it('menampilkan penolakan saat admin menghapus akunnya sendiri', function () {
    $admin = userWithRole(Role::Admin);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertSessionHasErrors(['user' => 'Anda tidak bisa menghapus akun sendiri.']);

    $this->assertModelExists($admin);
});
