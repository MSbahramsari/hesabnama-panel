<?php

namespace App\Enums;

enum BuyerStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case SystemAccepted = 'system_accepted';
    case NoReactionRequired = 'no_reaction_required';
    case ReactionImpossible = 'reaction_impossible';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار واکنش خریدار',
            self::Accepted => 'تأیید خریدار',
            self::Rejected => 'رد خریدار',
            self::SystemAccepted => 'تأیید سیستمی',
            self::NoReactionRequired => 'عدم نیاز به واکنش',
            self::ReactionImpossible => 'عدم امکان واکنش',
            self::Cancelled => 'ابطال شده',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Accepted => 'emerald',
            self::Rejected => 'rose',
            self::SystemAccepted, self::NoReactionRequired => 'emerald',
            self::ReactionImpossible => 'slate',
            self::Cancelled => 'slate',
        };
    }
}
