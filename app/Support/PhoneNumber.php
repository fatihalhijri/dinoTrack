<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneNumber
{
    /**
     * Ubah nomor WhatsApp ke format 62xxxxxxxxxx (docs/05): hapus spasi, tanda hubung,
     * titik, dan kurung, lalu `+62` menjadi `62` dan `08` menjadi `628`. Angka 0 setelah
     * kode negara (`+62 0812...`, kebiasaan saat mengetik) dibuang karena nomor seluler
     * Indonesia tidak pernah diawali `620`.
     * Bentuk lain dikembalikan apa adanya setelah dibersihkan agar ditolak validasi.
     */
    public static function normalize(string $phone): string
    {
        $cleaned = (string) preg_replace('/[\s\-.()]/', '', $phone);

        if (str_starts_with($cleaned, '+62')) {
            $cleaned = substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '620')) {
            return '62'.substr($cleaned, 3);
        }

        if (str_starts_with($cleaned, '08')) {
            return '62'.substr($cleaned, 1);
        }

        return $cleaned;
    }
}
