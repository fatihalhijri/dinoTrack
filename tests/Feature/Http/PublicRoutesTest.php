<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Halaman publik dan webhook dipisah dari halaman admin: tanpa grup `web` (session, cookie,
// CSRF) dan masing-masing diberi rate limit.

it('memisahkan route publik dari grup web dan memberinya rate limit', function (string $routeName, array $expectedMiddleware) {
    $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];

    expect($middleware)->not->toContain('web')
        ->and($middleware)->not->toContain('auth')
        ->and($middleware)->toContain(...$expectedMiddleware);
})->with([
    'halaman isolir' => ['isolation.show', ['throttle:isolation-page']],
    'halaman tagihan' => ['public-invoices.show', ['signed', 'throttle:public-invoice']],
    'status tagihan' => ['public-invoices.status', ['signed', 'throttle:public-invoice']],
    'bayar QRIS' => ['public-invoices.pay', ['signed', 'throttle:public-invoice-pay']],
    'webhook Midtrans' => ['webhooks.payments.midtrans', ['api', 'throttle:webhooks']],
]);
