<?php

declare(strict_types=1);

use App\Models\User;

it('tidak menyediakan halaman registrasi publik', function () {
    $response = $this->get('/register');

    $response->assertNotFound();
});

it('menolak pendaftaran akun dari luar', function () {
    $response = $this->post('/register', [
        'name' => 'Pengguna Asing',
        'email' => 'asing@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertNotFound();
    $this->assertGuest();
    $this->assertDatabaseMissing(User::class, ['email' => 'asing@example.com']);
});
