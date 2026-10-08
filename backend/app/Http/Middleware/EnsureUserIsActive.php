<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan pegawai yang dinonaktifkan admin dari session yang masih berjalan (termasuk
 * "ingat saya" dan login passkey), sehingga penonaktifan berlaku seketika di semua perangkat.
 */
class EnsureUserIsActive
{
    public const string MESSAGE = 'Akun Anda sudah dinonaktifkan. Hubungi admin.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isDeactivated()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', self::MESSAGE);
    }
}
