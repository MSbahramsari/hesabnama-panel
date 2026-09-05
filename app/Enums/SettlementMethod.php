<?php

namespace App\Enums;

enum SettlementMethod: string
{
    case Cash = 'cash';
    case Credit = 'credit';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدی',
            self::Credit => 'نسیه',
            self::Mixed => 'نقدی / نسیه',
        };
    }

    public function moadianCode(): int
    {
        return match ($this) {
            self::Cash => 1,
            self::Credit => 2,
            self::Mixed => 3,
        };
    }
}
