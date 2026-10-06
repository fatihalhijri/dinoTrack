<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Lock per pelanggan untuk perintah router. ShouldBeUnique hanya berlaku per kelas job, sehingga
 * tanpa lock ini isolir, aktivasi, dan nonaktif secret untuk pelanggan yang sama bisa berjalan
 * bersamaan dan saling menimpa profil di router.
 *
 * Reentrant di dalam satu proses: job yang di-dispatch dari dalam lock dan langsung dijalankan
 * (queue `sync`) tidak menunggu dirinya sendiri.
 */
final class CustomerNetworkLock
{
    /** Lebih lama dari satu operasi router terburuk (timeout koneksi + beberapa perintah). */
    public const int SECONDS = 120;

    public const int WAIT_SECONDS = 30;

    /** @var array<int, true> */
    private static array $held = [];

    public function __construct(
        private readonly int $waitSeconds = self::WAIT_SECONDS,
    ) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     *
     * @throws LockTimeoutException lock masih dipegang proses lain setelah waktu tunggu habis
     */
    public function run(Customer $customer, Closure $callback): mixed
    {
        if (isset(self::$held[$customer->id])) {
            return $callback();
        }

        return Cache::lock(self::key($customer), self::SECONDS)->block($this->waitSeconds, function () use ($customer, $callback): mixed {
            self::$held[$customer->id] = true;

            try {
                return $callback();
            } finally {
                unset(self::$held[$customer->id]);
            }
        });
    }

    public static function key(Customer $customer): string
    {
        return 'network:customer:'.$customer->id;
    }
}
