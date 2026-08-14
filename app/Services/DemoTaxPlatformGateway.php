<?php

namespace App\Services;

use App\Contracts\TaxPlatformGateway;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\InquiryResult;
use App\Services\Moadian\SubmissionResult;
use Illuminate\Support\Str;

class DemoTaxPlatformGateway implements TaxPlatformGateway
{
    public function lookupCustomer(User $user, string $economicCode): ?array
    {
        return [
            '411111111111' => [
                'name' => 'شرکت راهکار مالی آریا',
                'national_id' => '14001234567',
                'type' => 'legal',
                'address' => 'تهران، میدان ونک، خیابان ملاصدرا',
                'postal_code' => '1991912345',
            ],
            '422222222222' => [
                'name' => 'بازرگانی سپهر ایرانیان',
                'national_id' => '10104567891',
                'type' => 'legal',
                'address' => 'اصفهان، خیابان چهارباغ بالا',
                'postal_code' => '8174612345',
            ],
        ][$economicCode] ?? null;
    }

    public function lookupGood(User $user, string $commodityCode): ?array
    {
        return [
            '10000001' => ['name' => 'خدمات مشاوره مالیاتی', 'unit' => 'ساعت', 'unit_price' => 25000000, 'tax_rate' => 10],
            '10000002' => ['name' => 'نرم‌افزار حسابداری ابری', 'unit' => 'اشتراک', 'unit_price' => 85000000, 'tax_rate' => 10],
            '10000003' => ['name' => 'خدمات پشتیبانی سامانه', 'unit' => 'ماه', 'unit_price' => 18000000, 'tax_rate' => 10],
        ][$commodityCode] ?? null;
    }

    public function submit(Invoice $invoice): SubmissionResult
    {
        return new SubmissionResult((string) Str::uuid(), (string) Str::uuid());
    }

    public function inquire(Invoice $invoice): InquiryResult
    {
        return new InquiryResult('SUCCESS', 'SUCCESS');
    }

    public function isDemo(): bool
    {
        return true;
    }
}
