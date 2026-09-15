<?php

namespace App\Enums;

enum ElectronicInvoiceType: int
{
    case Full = 1;
    case Consumer = 2;
    case PosReceipt = 3;

    public function label(): string
    {
        return match ($this) {
            self::Full => 'نوع اول — فروش به فعال اقتصادی',
            self::Consumer => 'نوع دوم — فروش به مصرف‌کننده نهایی',
            self::PosReceipt => 'نوع سوم — رسید پایانه فروشگاهی',
        };
    }
}
