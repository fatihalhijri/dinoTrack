<?php

declare(strict_types=1);

namespace App\Support;

final class ProrataCalculator
{
    /**
     * Harga periode pertama (docs/04 "Prorata periode pertama"):
     * harga × hari terpakai ÷ hari periode penuh, dibulatkan ke atas ke kelipatan Rp100.
     * Dihitung dengan integer agar tidak ada galat pembulatan float.
     */
    public static function calculate(int $monthlyPrice, int $usedDays, int $fullPeriodDays): int
    {
        if ($usedDays >= $fullPeriodDays) {
            return $monthlyPrice;
        }

        $exactCeil = intdiv($monthlyPrice * $usedDays + $fullPeriodDays - 1, $fullPeriodDays);

        // Pembulatan ke Rp100 tidak boleh melebihi harga sebulan penuh.
        return min(Money::ceilToHundreds($exactCeil), $monthlyPrice);
    }
}
