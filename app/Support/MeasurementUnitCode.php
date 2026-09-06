<?php

namespace App\Support;

class MeasurementUnitCode
{
    public const Each = '1627';

    public static function resolve(mixed $officialCode = null): string
    {
        $officialCode = self::digitsOnly($officialCode);

        if ($officialCode !== '') {
            return $officialCode;
        }

        $configuredCode = self::digitsOnly(config('services.moadian.default_measurement_unit_code'));

        return $configuredCode !== '' ? $configuredCode : self::Each;
    }

    private static function digitsOnly(mixed $value): string
    {
        $latinValue = strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return preg_match('/^\d{1,8}$/', $latinValue) === 1 ? $latinValue : '';
    }
}
