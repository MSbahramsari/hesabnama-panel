<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

final class JalaliDate
{
    public static function format(DateTimeInterface|string|null $value, string $format = 'Y/m/d'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }

        [$year, $month, $day] = self::fromGregorian(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d'),
        );

        return strtr($format, [
            'Y' => sprintf('%04d', $year),
            'm' => sprintf('%02d', $month),
            'd' => sprintf('%02d', $day),
            'H' => $date->format('H'),
            'i' => $date->format('i'),
            's' => $date->format('s'),
        ]);
    }

    public static function toGregorianDate(?string $value): ?string
    {
        $value = self::normalizeDigits(trim((string) $value));
        $value = str_replace(['-', '.'], '/', $value);

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($year < 1200 || $year > 1700 || $month < 1 || $month > 12 || $day < 1 || $day > self::daysInMonth($year, $month)) {
            return null;
        }

        [$gregorianYear, $gregorianMonth, $gregorianDay] = self::toGregorian($year, $month, $day);

        return sprintf('%04d-%02d-%02d', $gregorianYear, $gregorianMonth, $gregorianDay);
    }

    public static function catalogDate(?string $value): ?string
    {
        $value = self::normalizeDigits(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $value, $matches) !== 1) {
            return $value;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];

        if ($year >= 1700) {
            return self::format(sprintf('%04d-%02d-%02d', $year, $month, $day));
        }

        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }

    /** @return array{int, int, int} */
    public static function fromGregorian(int $year, int $month, int $day): array
    {
        $gregorianMonthDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($year > 1600) {
            $jalaliYear = 979;
            $year -= 1600;
        } else {
            $jalaliYear = 0;
            $year -= 621;
        }

        $adjustedYear = $month > 2 ? $year + 1 : $year;
        $days = (365 * $year)
            + intdiv($adjustedYear + 3, 4)
            - intdiv($adjustedYear + 99, 100)
            + intdiv($adjustedYear + 399, 400)
            - 80
            + $day
            + $gregorianMonthDays[$month - 1];

        $jalaliYear += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jalaliYear += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jalaliYear += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jalaliMonth = 1 + intdiv($days, 31);
            $jalaliDay = 1 + ($days % 31);
        } else {
            $jalaliMonth = 7 + intdiv($days - 186, 30);
            $jalaliDay = 1 + (($days - 186) % 30);
        }

        return [$jalaliYear, $jalaliMonth, $jalaliDay];
    }

    /** @return array{int, int, int} */
    public static function toGregorian(int $year, int $month, int $day): array
    {
        $year += 1595;
        $days = -355668
            + (365 * $year)
            + (intdiv($year, 33) * 8)
            + intdiv(($year % 33) + 3, 4)
            + $day
            + ($month < 7 ? ($month - 1) * 31 : (($month - 7) * 30) + 186);

        $gregorianYear = 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $days--;
            $gregorianYear += 100 * intdiv($days, 36524);
            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $gregorianYear += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gregorianYear += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gregorianDay = $days + 1;
        $monthDays = [0, 31, self::isGregorianLeapYear($gregorianYear) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        for ($gregorianMonth = 1; $gregorianMonth <= 12 && $gregorianDay > $monthDays[$gregorianMonth]; $gregorianMonth++) {
            $gregorianDay -= $monthDays[$gregorianMonth];
        }

        return [$gregorianYear, $gregorianMonth, $gregorianDay];
    }

    private static function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        [$startYear, $startMonth, $startDay] = self::toGregorian($year, 1, 1);
        [$nextYear, $nextMonth, $nextDay] = self::toGregorian($year + 1, 1, 1);
        $start = CarbonImmutable::create($startYear, $startMonth, $startDay, 0, 0, 0, 'UTC');
        $next = CarbonImmutable::create($nextYear, $nextMonth, $nextDay, 0, 0, 0, 'UTC');

        return (int) round($start->diffInDays($next)) === 366 ? 30 : 29;
    }

    private static function isGregorianLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    private static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
