<?php

use App\Support\JalaliDate;

it('converts gregorian dates to jalali dates', function () {
    expect(JalaliDate::format('2026-08-15'))->toBe('1405/05/24')
        ->and(JalaliDate::format('2026-08-15 14:25:00', 'Y/m/d H:i'))->toBe('1405/05/24 14:25');
});

it('converts jalali input back to the database date format', function () {
    expect(JalaliDate::toGregorianDate('۱۴۰۵/۰۵/۲۴'))->toBe('2026-08-15')
        ->and(JalaliDate::toGregorianDate('1405-05-24'))->toBe('2026-08-15')
        ->and(JalaliDate::toGregorianDate('1399/12/30'))->toBe('2021-03-20')
        ->and(JalaliDate::toGregorianDate('1405/13/01'))->toBeNull();
});

it('keeps jalali catalog dates and converts gregorian catalog dates', function () {
    expect(JalaliDate::catalogDate('1405/05/24'))->toBe('1405/05/24')
        ->and(JalaliDate::catalogDate('2026-08-15'))->toBe('1405/05/24');
});
