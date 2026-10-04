<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentChargeStatus: string
{
    case Pending = 'pending';
    case Settled = 'settled';
    case Expired = 'expired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Settled => 'Lunas',
            self::Expired => 'Kedaluwarsa',
            self::Failed => 'Gagal',
        };
    }
}
