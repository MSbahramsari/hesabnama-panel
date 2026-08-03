<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case PendingSend = 'pending_send';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case MoadianError = 'moadian_error';
    case Confirmed = 'confirmed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::PendingSend => 'در انتظار ارسال',
            self::AwaitingConfirmation => 'منتظر تأیید مودیان',
            self::MoadianError => 'خطای مودیان',
            self::Confirmed => 'تأیید شده توسط مودیان',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::PendingSend => 'amber',
            self::AwaitingConfirmation => 'blue',
            self::MoadianError => 'rose',
            self::Confirmed => 'emerald',
        };
    }
}
