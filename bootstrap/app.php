<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\SettingsRepository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Webhook dari layanan luar: grup `api` (tanpa session dan CSRF) dengan rate limit sendiri.
        then: function (): void {
            Route::middleware(['api', 'throttle:webhooks'])
                ->prefix('webhooks')
                ->name('webhooks.')
                ->group(base_path('routes/webhooks.php'));

            // Halaman publik pelanggan (halaman isolir): tanpa session karena setiap request
            // HTTP perangkat yang diisolir diarahkan ke sini.
            Route::group([], base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Global: juga berlaku untuk halaman publik dan webhook yang tidak memakai grup `web`.
        $middleware->append(AddSecurityHeaders::class);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Link tagihan publik yang rusak/diubah: halaman ramah pelanggan, bukan 403 polos.
        $exceptions->render(fn (InvalidSignatureException $exception, Request $request) => $request->routeIs('public-invoices.show')
            ? response()->view('public.link-invalid', ['businessName' => app(SettingsRepository::class)->businessName()], 403)
            : null);
    })->create();
