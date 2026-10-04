<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Isolated = 'isolated';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pemasangan',
            self::Active => 'Aktif',
            self::Isolated => 'Diisolir',
            self::Terminated => 'Berhenti',
        };
    }
}
