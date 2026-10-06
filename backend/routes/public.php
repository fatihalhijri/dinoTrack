<?php

declare(strict_types=1);

use App\Http\Controllers\IsolationPageController;
use Illuminate\Support\Facades\Route;

// Halaman publik pelanggan (Blade biasa). Didaftarkan di bootstrap/app.php tanpa grup `web`:
// tanpa session, cookie, dan CSRF.

Route::get('isolir', IsolationPageController::class)
    ->middleware('throttle:isolation-page')
    ->name('isolation.show');
