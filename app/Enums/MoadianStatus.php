<?php

namespace App\Enums;

enum MoadianStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در حال پردازش مودیان',
            self::Success => 'پذیرفته‌شده توسط مودیان',
            self::Failed => 'ردشده توسط مودیان',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'blue',
            self::Success => 'emerald',
            self::Failed => 'rose',
        };
    }
}
