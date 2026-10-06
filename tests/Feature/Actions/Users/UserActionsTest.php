<?php

declare(strict_types=1);

use App\Actions\Users\CreateUser;
use App\Actions\Users\DeactivateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\ReactivateUser;
use App\Actions\Users\UpdateUser;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

it('membuat akun pegawai terverifikasi dengan role dan mencatat pembuatnya', function () {
    $admin = userWithRole(Role::Admin);

    $user = app(CreateUser::class)->handle(['name' => 'Kasir Baru', 'email' => 'kasir@isp.test', 'password' => 'rahasia-123'], Role::Kasir, $admin);

    expect($user->hasRole(Role::Kasir))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('rahasia-123', $user->password))->toBeTrue();
    $log = ActivityLog::query()->where('action', 'user.created')->sole();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->properties)->toEqual(['email' => 'kasir@isp.test', 'role' => 'kasir']);
});

it('mengubah data dan role pegawai tanpa mencatat password', function () {
    $admin = userWithRole(Role::Admin);
    $user = userWithRole(Role::Kasir);

    app(UpdateUser::class)->handle($user, ['name' => 'Nama Baru', 'email' => $user->email, 'password' => 'password-baru'], Role::Teknisi, $admin);

    $user->refresh();
    expect($user->name)->toBe('Nama Baru')
        ->and($user->getRoleNames()->all())->toBe(['teknisi'])
        ->and(Hash::check('password-baru', $user->password))->toBeTrue();
    $changes = ActivityLog::query()->where('action', 'user.updated')->sole()->properties['changes'];
    expect($changes['role'])->toBe(['kasir', 'teknisi'])
        ->and($changes['password'])->toBe(['***', '***']);
});

it('mempertahankan password lama jika password kosong', function () {
    $user = userWithRole(Role::Kasir);
    $hash = $user->password;

    app(UpdateUser::class)->handle($user, ['name' => 'Nama Baru', 'email' => $user->email, 'password' => null], Role::Kasir, userWithRole(Role::Admin));

    expect($user->refresh()->password)->toBe($hash);
});

it('menolak admin mengubah role akunnya sendiri', function () {
    $admin = userWithRole(Role::Admin);
    userWithRole(Role::Admin);

    app(UpdateUser::class)->handle($admin, ['name' => $admin->name, 'email' => $admin->email], Role::Kasir, $admin);
})->throws(ValidationException::class, 'Anda tidak bisa mengubah role akun sendiri.');

it('menolak menurunkan admin aktif terakhir', function () {
    $lastAdmin = userWithRole(Role::Admin);
    $deactivatedAdmin = userWithRole(Role::Admin);
    $deactivatedAdmin->forceFill(['deactivated_at' => now()])->save();

    app(UpdateUser::class)->handle($lastAdmin, ['name' => $lastAdmin->name, 'email' => $lastAdmin->email], Role::Kasir, $deactivatedAdmin);
})->throws(ValidationException::class, 'Harus ada minimal satu admin aktif.');

it('menonaktifkan pegawai dan mengganti token ingat saya', function () {
    $admin = userWithRole(Role::Admin);
    $user = userWithRole(Role::Kasir);
    $token = $user->getRememberToken();

    app(DeactivateUser::class)->handle($user, $admin);

    $user->refresh();
    expect($user->isDeactivated())->toBeTrue()
        ->and($user->getRememberToken())->not->toBe($token);
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'user.deactivated', 'subject_id' => $user->id, 'user_id' => $admin->id]);
});

it('menolak penonaktifan yang tidak sah', function (Closure $scenario, string $message) {
    expect($scenario)->toThrow(ValidationException::class, $message);
})->with([
    'akun sendiri' => [function () {
        $admin = userWithRole(Role::Admin);
        app(DeactivateUser::class)->handle($admin, $admin);
    }, 'Anda tidak bisa menonaktifkan akun sendiri.'],
    'sudah nonaktif' => [function () {
        app(DeactivateUser::class)->handle(User::factory()->deactivated()->create(), userWithRole(Role::Admin));
    }, 'Akun sudah nonaktif.'],
    'admin aktif terakhir' => [function () {
        $lastAdmin = userWithRole(Role::Admin);
        $by = userWithRole(Role::Admin);
        $by->forceFill(['deactivated_at' => now()])->save();
        app(DeactivateUser::class)->handle($lastAdmin, $by);
    }, 'Admin aktif terakhir tidak bisa dinonaktifkan.'],
]);

it('mengaktifkan kembali pegawai yang dinonaktifkan', function () {
    $user = User::factory()->deactivated()->create();

    app(ReactivateUser::class)->handle($user, userWithRole(Role::Admin));

    expect($user->refresh()->isDeactivated())->toBeFalse();
    $this->assertDatabaseHas(ActivityLog::class, ['action' => 'user.reactivated', 'subject_id' => $user->id]);
});

it('menghapus akun yang belum punya jejak aktivitas', function () {
    $admin = userWithRole(Role::Admin);
    $user = userWithRole(Role::Teknisi);

    app(DeleteUser::class)->handle($user, $admin);

    $this->assertModelMissing($user);
    expect(ActivityLog::query()->where('action', 'user.deleted')->sole()->properties)
        ->toMatchArray(['user_id' => $user->id, 'email' => $user->email]);
});

it('menolak menghapus akun yang punya jejak audit', function (Closure $trail) {
    $user = userWithRole(Role::Kasir);
    $trail($user);

    expect(fn () => app(DeleteUser::class)->handle($user, userWithRole(Role::Admin)))
        ->toThrow(ValidationException::class, 'User sudah punya jejak aktivitas sehingga tidak bisa dihapus. Nonaktifkan sebagai gantinya.');
    $this->assertModelExists($user);
})->with([
    'pembayaran yang dicatat' => [fn (User $user) => Payment::factory()->cash()->create(['received_by' => $user->id])],
    'activity log' => [fn (User $user) => ActivityLog::query()->create(['user_id' => $user->id, 'action' => 'customer.created'])],
]);

it('menolak admin menghapus akunnya sendiri', function () {
    $admin = userWithRole(Role::Admin);

    app(DeleteUser::class)->handle($admin, $admin);
})->throws(ValidationException::class, 'Anda tidak bisa menghapus akun sendiri.');
