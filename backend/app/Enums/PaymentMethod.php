<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Qris = 'qris';
    case Cash = 'cash';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            self::Cash => 'Tunai',
            self::Transfer => 'Transfer bank',
        };
    }
}
