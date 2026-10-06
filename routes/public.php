<?php

declare(strict_types=1);

use App\Http\Controllers\IsolationPageController;
use App\Http\Controllers\PublicInvoiceController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Halaman publik pelanggan (Blade biasa). Didaftarkan di bootstrap/app.php tanpa grup `web`:
// tanpa session, cookie, dan CSRF.

Route::get('isolir', IsolationPageController::class)
    ->middleware('throttle:isolation-page')
    ->name('isolation.show');

// Signed URL tanpa masa berlaku (A4), dibuat oleh App\Support\InvoicePaymentLink. Tanpa grup `web`,
// route model binding perlu dipasang sendiri.
Route::prefix('tagihan/{invoice}')
    ->middleware(['signed', SubstituteBindings::class])
    ->name('public-invoices.')
    ->group(function (): void {
        Route::get('/', [PublicInvoiceController::class, 'show'])
            ->middleware('throttle:public-invoice')
            ->name('show');

        Route::get('status', [PublicInvoiceController::class, 'status'])
            ->middleware('throttle:public-invoice')
            ->name('status');

        Route::post('qris', [PublicInvoiceController::class, 'pay'])
            ->middleware('throttle:public-invoice-pay')
            ->name('pay');
    });
