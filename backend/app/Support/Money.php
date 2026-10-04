<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    /** Format rupiah, contoh 150000 menjadi "Rp150.000". */
    public static function format(int $amount): string
    {
        $formatted = number_format(abs($amount), 0, ',', '.');

        return ($amount < 0 ? '-' : '').'Rp'.$formatted;
    }

    /** Pembulatan ke atas ke kelipatan Rp100 (dipakai untuk prorata). */
    public static function ceilToHundreds(int $amount): int
    {
        return intdiv($amount + 99, 100) * 100;
    }
}
