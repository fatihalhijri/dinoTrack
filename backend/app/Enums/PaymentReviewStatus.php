<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentReviewStatus: string
{
    case None = 'none';
    case NeedsReview = 'needs_review';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Normal',
            self::NeedsReview => 'Perlu tinjauan',
            self::Resolved => 'Sudah ditinjau',
        };
    }
}
