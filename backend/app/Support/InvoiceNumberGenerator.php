<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Nomor invoice `INV/YYYY/MM/NNNNN` (A1): urutan 5 digit yang di-reset tiap bulan terbit.
 * Aman dari race condition karena memakai SequenceGenerator di transaksi pemanggil.
 */
final class InvoiceNumberGenerator
{
    public function __construct(
        private readonly SequenceGenerator $sequence,
    ) {}

    public function next(CarbonInterface $issuedAt): string
    {
        $sequence = $this->sequence->next('invoice:'.$issuedAt->format('Y-m'));

        return sprintf('INV/%s/%05d', $issuedAt->format('Y/m'), $sequence);
    }
}
