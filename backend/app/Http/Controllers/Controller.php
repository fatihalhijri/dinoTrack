<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Pelaku aksi untuk Action yang mewajibkan user (route admin selalu di balik middleware `auth`).
     */
    protected function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Notifikasi sekali tampil untuk frontend (`flash.toast`, lihat resources/js/hooks/use-flash-toast.ts).
     *
     * @param  'success'|'info'|'warning'|'error'  $type
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
