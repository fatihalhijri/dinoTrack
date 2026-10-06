<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kelompok umur tunggakan: jumlah hari sejak jatuh tempo (invoice yang lewat jatuh tempo
 * kemarin berumur 1 hari).
 */
enum OutstandingAgeBucket: string
{
    case UpToSevenDays = '0-7';
    case EightToThirtyDays = '8-30';
    case OverThirtyDays = '31+';

    public function label(): string
    {
        return match ($this) {
            self::UpToSevenDays => '0–7 hari',
            self::EightToThirtyDays => '8–30 hari',
            self::OverThirtyDays => 'Lebih dari 30 hari',
        };
    }

    /**
     * Batas atas umur (inklusif); null untuk kelompok terakhir.
     */
    public function maxDays(): ?int
    {
        return match ($this) {
            self::UpToSevenDays => 7,
            self::EightToThirtyDays => 30,
            self::OverThirtyDays => null,
        };
    }

    public static function forAgeDays(int $ageDays): self
    {
        foreach (self::cases() as $bucket) {
            $maxDays = $bucket->maxDays();

            if ($maxDays === null || $ageDays <= $maxDays) {
                return $bucket;
            }
        }

        return self::OverThirtyDays;
    }
}
