<?php

declare(strict_types=1);

use App\Models\PaymentNotification;
use App\Models\Router;
use Illuminate\Support\Facades\Log;

it('keluar sukses tanpa menulis log saat semua sehat', function () {
    Log::spy();

    $this->artisan('billing:health')
        ->expectsOutputToContain('Kesehatan aplikasi: 0 gagal, 0 peringatan.')
        ->assertSuccessful();

    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('warning');
});

it('tetap keluar sukses dan mencatat peringatan saat router tidak terjangkau', function () {
    fakeNetwork()->connectable = false;
    Router::factory()->create(['name' => 'Pusat']);
    Log::spy();

    $this->artisan('billing:health')
        ->expectsOutputToContain('Kesehatan aplikasi: 0 gagal, 1 peringatan.')
        ->assertSuccessful();

    Log::shouldHaveReceived('warning')->once()->with('Pemeriksaan kesehatan memberi peringatan.', [
        'check' => 'Router: Pusat',
        'message' => 'Tidak bisa dihubungi.',
    ]);
});

it('keluar gagal dan mencatat error saat pembayaran tertahan', function () {
    $this->freezeTime();
    PaymentNotification::factory()->create(['created_at' => now()->subMinutes(20)]);
    Log::spy();

    $this->artisan('billing:health')
        ->expectsOutputToContain('Kesehatan aplikasi: 1 gagal, 0 peringatan.')
        ->assertFailed();

    Log::shouldHaveReceived('error')->once()->with('Pemeriksaan kesehatan gagal.', Mockery::on(
        fn (array $context): bool => $context['check'] === 'Notifikasi pembayaran',
    ));
});
