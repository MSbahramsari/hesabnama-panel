<?php

namespace App\Enums;

enum Plan: string
{
    case Starter = 'starter';
    case Business = 'business';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Starter => 'پایه',
            self::Business => 'کسب‌وکار',
            self::Enterprise => 'سازمانی',
        };
    }
}
