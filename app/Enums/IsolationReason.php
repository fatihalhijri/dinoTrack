<?php

declare(strict_types=1);

namespace App\Enums;

enum IsolationReason: string
{
    /** Isolir otomatis karena tunggakan; dibuka otomatis saat lunas. */
    case Overdue = 'overdue';

    /** Isolir oleh admin; hanya dibuka manual oleh admin. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Overdue => 'Tunggakan',
            self::Manual => 'Manual oleh admin',
        };
    }
}
