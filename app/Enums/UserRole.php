<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدیر سامانه',
            self::Member => 'کاربر',
        };
    }
}
