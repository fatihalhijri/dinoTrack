<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum dibayar',
            self::Paid => 'Lunas',
            self::Overdue => 'Lewat jatuh tempo',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Status yang masih harus ditagih.
     *
     * @return list<self>
     */
    public static function outstanding(): array
    {
        return [self::Unpaid, self::Overdue];
    }
}
