<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Exceptions\RouterCommandException;
use App\Exceptions\RouterUnreachableException;
use App\Exceptions\SecretNotFoundException;
use App\Models\Customer;
use App\Support\ActivityLogger;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Penanganan kegagalan bersama untuk job yang memerintah router.
 *
 * RouterUnreachableException dibiarkan lolos agar queue mencoba ulang. Secret yang tidak ada
 * dan perintah yang ditolak router tidak akan sembuh dengan diulang (username salah, profil
 * belum dibuat), sehingga job langsung gagal dan admin diberi tanda.
 *
 * @property-read Customer $customer
 *
 * @method void fail(string|Throwable|null $exception = null)
 */
trait HandlesRouterFailures
{
    /**
     * @param  Closure(): mixed  $command
     */
    protected function runRouterCommand(Closure $command): void
    {
        try {
            $command();
        } catch (SecretNotFoundException|RouterCommandException $exception) {
            $this->fail($exception);
        }
    }

    /**
     * Tanda untuk admin: kolom `network_error_at` di pelanggan (dikosongkan saat perintah router
     * berikutnya berhasil), log error, dan activity log. Kolom dan activity log ikut tampil ke
     * kasir/teknisi di halaman pelanggan, sehingga alamat router hanya masuk log aplikasi.
     */
    protected function flagRouterFailure(string $action, string $message, ?Throwable $exception): void
    {
        $detail = $exception instanceof RouterUnreachableException ? $exception->summary() : $exception?->getMessage();
        $error = Str::limit(trim($message.' '.$detail), 250);

        Log::error($message, [
            'customer_id' => $this->customer->id,
            'pppoe_username' => $this->customer->pppoe_username,
            'error' => $exception?->getMessage(),
        ]);

        Customer::withTrashed()->whereKey($this->customer->id)->update([
            'network_error_at' => now(),
            'network_error' => $error,
        ]);

        app(ActivityLogger::class)->log($action, $this->customer, null, [
            'error' => $detail,
        ]);
    }
}
