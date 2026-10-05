<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Penghitung berurutan di tabel `sequences` (kode pelanggan, nomor invoice per bulan).
 */
final class SequenceGenerator
{
    /**
     * Baris dikunci sampai transaksi terluar selesai, sehingga pemanggil bersamaan menunggu
     * giliran dan tidak pernah mendapat nilai yang sama. Jika transaksi terluar gagal,
     * nilainya ikut di-rollback sehingga tidak ada nomor yang terbuang.
     */
    public function next(string $key): int
    {
        return DB::transaction(function () use ($key): int {
            // Satu perintah INSERT ... ON DUPLICATE KEY UPDATE langsung mengambil exclusive lock.
            // Pola "insertOrIgnore lalu SELECT FOR UPDATE" mengambil shared lock lebih dulu dan
            // menyebabkan deadlock saat dua transaksi sama-sama menaikkannya ke exclusive.
            DB::table('sequences')->upsert(
                [['key' => $key, 'last_value' => 1, 'created_at' => now(), 'updated_at' => now()]],
                ['key'],
                ['last_value' => DB::raw('`last_value` + 1'), 'updated_at' => now()],
            );

            return (int) DB::table('sequences')->where('key', $key)->lockForUpdate()->value('last_value');
        });
    }
}
