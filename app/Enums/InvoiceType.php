<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Original = 'original';
    case Correction = 'correction';
    case Cancellation = 'cancellation';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'اصلی',
            self::Correction => 'اصلاحی',
            self::Cancellation => 'ابطالی',
        };
    }

    public function moadianCode(): int
    {
        return match ($this) {
            self::Original => 1,
            self::Correction => 2,
            self::Cancellation => 3,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Original => 'slate',
            self::Correction => 'blue',
            self::Cancellation => 'rose',
        };
    }
}
