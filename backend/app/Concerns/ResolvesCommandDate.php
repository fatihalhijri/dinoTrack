<?php

declare(strict_types=1);

namespace App\Concerns;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Opsi `--date` pada command billing: hanya untuk simulasi di luar production. Di production
 * command selalu memakai hari ini dan hari yang terlewat ditangani oleh catch-up.
 *
 * @mixin Command
 */
trait ResolvesCommandDate
{
    /**
     * @throws InvalidArgumentException jika format salah atau dipakai di production
     */
    protected function resolveDate(): CarbonImmutable
    {
        $option = $this->option('date');

        if (! is_string($option) || $option === '') {
            return today();
        }

        if (app()->isProduction()) {
            throw new InvalidArgumentException('Opsi --date hanya untuk simulasi dan tidak boleh dipakai di production.');
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $option);
        } catch (InvalidFormatException) {
            $date = null;
        }

        // Tanggal yang "meluap" (misalnya 2026-02-30) diubah Carbon ke bulan berikutnya, jadi dibandingkan ulang.
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $option) {
            throw new InvalidArgumentException("Format --date harus YYYY-MM-DD, diterima: {$option}.");
        }

        return $date;
    }
}
