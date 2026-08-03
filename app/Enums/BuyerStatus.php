<?php

namespace App\Enums;

enum BuyerStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'تأیید خریدار',
            self::Rejected => 'رد خریدار',
            self::Cancelled => 'ابطال شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Accepted => 'emerald',
            self::Rejected => 'rose',
            self::Cancelled => 'slate',
        };
    }
}
