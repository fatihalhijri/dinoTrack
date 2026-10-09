<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

// Error di halaman admin tampil sebagai halaman Inertia `errors/error` (AppServiceProvider::
// configureErrorPages); halaman publik, webhook, JSON, dan mode maintenance memakai respons bawaan.

beforeEach(function () {
    Route::middleware('web')->group(function (): void {
        Route::get('_uji/gagal', fn () => throw new RuntimeException('Galat uji halaman error'));
        Route::post('_uji/kedaluwarsa', fn () => abort(419));
        Route::post('_uji/terlalu-sering', fn () => abort(429));
    });
});

it('menampilkan halaman error 404 untuk URL yang tidak ada', function () {
    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error', true)
            ->where('status', 404)
            ->where('auth.user', null));
});

it('menampilkan halaman error 403 dengan data user untuk role yang tidak berhak', function () {
    $kasir = userWithRole(Role::Kasir);

    $this->actingAs($kasir)
        ->get(route('users.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error', true)
            ->where('status', 403)
            ->where('auth.user.id', $kasir->id));
});

it('menampilkan halaman error 500 tanpa detail galat saat debug mati', function () {
    config(['app.debug' => false]);

    $this->actingAs(userWithRole(Role::Admin))
        ->get('/_uji/gagal')
        ->assertInternalServerError()
        ->assertDontSee('Galat uji halaman error')
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error', true)
            ->where('status', 500));
});

it('tetap menampilkan halaman debug Laravel untuk error 500 saat debug menyala', function () {
    config(['app.debug' => true]);

    $this->actingAs(userWithRole(Role::Admin))
        ->get('/_uji/gagal')
        ->assertInternalServerError()
        ->assertSee('Galat uji halaman error');
});

it('mengembalikan formulir Inertia yang kena 419 atau 429 ke halaman asal dengan pesan', function (string $uri, string $message) {
    $this->actingAs(userWithRole(Role::Admin))
        ->from(route('dashboard'))
        ->withHeader('X-Inertia', 'true')
        ->post($uri)
        ->assertStatus(303)
        ->assertRedirect(route('dashboard'))
        ->assertInertiaFlash('toast', ['type' => 'warning', 'message' => $message]);
})->with([
    'sesi kedaluwarsa' => ['/_uji/kedaluwarsa', 'Sesi halaman sudah kedaluwarsa. Silakan ulangi.'],
    'terlalu banyak permintaan' => ['/_uji/terlalu-sering', 'Terlalu banyak permintaan. Coba lagi sebentar lagi.'],
]);

it('menampilkan halaman error 419 untuk request yang bukan Inertia', function () {
    $this->actingAs(userWithRole(Role::Admin))
        ->post('/_uji/kedaluwarsa')
        ->assertStatus(419)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/error', true)
            ->where('status', 419));
});

it('membalas JSON untuk request JSON, bukan halaman error', function () {
    $this->actingAs(userWithRole(Role::Kasir))
        ->getJson(route('users.index'))
        ->assertForbidden()
        ->assertJsonStructure(['message']);
});

it('tidak mengubah halaman error halaman publik dan webhook', function () {
    $this->get('/tagihan/1?signature=palsu')
        ->assertForbidden()
        ->assertViewIs('public.link-invalid');

    $this->post('/webhooks/tidak-ada')
        ->assertNotFound()
        ->assertDontSee('errors\/error', false);
});

it('menampilkan halaman maintenance Blade tanpa aset Vite selama deploy', function () {
    config(['app.debug' => false]);
    $this->app->maintenanceMode()->activate([]);

    try {
        $this->get(route('dashboard'))
            ->assertServiceUnavailable()
            ->assertSee('Sedang pemeliharaan')
            ->assertDontSee('data-page', false);
    } finally {
        $this->app->maintenanceMode()->deactivate();
    }
});
