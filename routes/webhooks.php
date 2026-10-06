<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

// Didaftarkan di bootstrap/app.php tanpa grup `web`: tanpa session, cookie, dan CSRF.

Route::post('payments/midtrans', MidtransWebhookController::class)->name('payments.midtrans');
