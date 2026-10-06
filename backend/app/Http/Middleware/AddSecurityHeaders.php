<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua respons (admin, halaman publik, webhook). Halaman tagihan
 * publik memakai signed URL tanpa masa berlaku, sehingga URL lengkapnya tidak boleh ikut
 * terkirim sebagai Referer ke situs lain dan halamannya tidak boleh dibingkai situs lain.
 */
class AddSecurityHeaders
{
    public const array HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value, replace: false);
        }

        return $response;
    }
}
