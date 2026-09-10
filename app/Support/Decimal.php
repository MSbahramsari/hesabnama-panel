<?php

namespace App\Support;

class Decimal
{
    public static function format(float|int|string|null $value, int $precision = 2): string
    {
        $formatted = rtrim(rtrim(number_format((float) $value, $precision, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
