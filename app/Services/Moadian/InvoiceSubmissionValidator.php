<?php

namespace App\Services\Moadian;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SettlementMethod;
use App\Exceptions\MoadianConfigurationException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\CarbonImmutable;

class InvoiceSubmissionValidator
{
    public const NORMAL_SUBMISSION_WINDOW_DAYS = 12;

    public function assertValid(Invoice $invoice): void
    {
        $invoice->loadMissing(['customer', 'items.good', 'referenceInvoice.items']);

        $invoiceDate = CarbonImmutable::parse($invoice->invoice_date->format('Y-m-d'), 'Asia/Tehran')->startOfDay();
        $today = CarbonImmutable::today('Asia/Tehran');
        $submissionWindowDays = (int) config('services.moadian.normal_submission_window_days', self::NORMAL_SUBMISSION_WINDOW_DAYS);

        if ($invoiceDate->isAfter($today)) {
            throw new MoadianConfigurationException('تاریخ صورتحساب نمی‌تواند بعد از تاریخ امروز باشد.');
        }

        if ($invoiceDate->isBefore($today->subDays($submissionWindowDays))) {
            throw new MoadianConfigurationException(
                "مهلت عادی ارسال این صورتحساب گذشته است. فاصله تاریخ صدور تا ارسال نباید بیشتر از {$submissionWindowDays} روز باشد؛ صورتحساب‌های قدیمی فقط با قواعد استثنایی ماده ۹ قابل ثبت هستند.",
            );
        }

        if ($invoice->customer === null) {
            throw new MoadianConfigurationException('خریدار صورتحساب مشخص نشده است.');
        }

        if (preg_match('/^(?:\d{11}|\d{14})$/', (string) $invoice->customer->economic_code) !== 1) {
            throw new MoadianConfigurationException('شماره اقتصادی خریدار باید مطابق قالب رسمی، ۱۱ یا ۱۴ رقم باشد.');
        }

        $nationalId = (string) $invoice->customer->national_id;

        if ($nationalId !== '') {
            $expectedLength = $invoice->customer->type === 'individual' ? 10 : 11;

            if (preg_match("/^\\d{{$expectedLength}}$/", $nationalId) !== 1) {
                throw new MoadianConfigurationException("شناسه ملی خریدار با نوع شخصیت انتخاب‌شده سازگار نیست و باید {$expectedLength} رقم باشد.");
            }
        }

        if (filled($invoice->customer->postal_code) && preg_match('/^\d{10}$/', (string) $invoice->customer->postal_code) !== 1) {
            throw new MoadianConfigurationException('کد پستی خریدار باید دقیقاً ۱۰ رقم باشد.');
        }

        if ($invoice->items->isEmpty()) {
            throw new MoadianConfigurationException('صورتحساب بدون قلم قابل ارسال به سامانه مودیان نیست.');
        }

        if ($invoice->items->count() > 100) {
            throw new MoadianConfigurationException('هر صورتحساب در حساب‌نما حداکثر می‌تواند ۱۰۰ ردیف داشته باشد.');
        }

        foreach ($invoice->items as $index => $item) {
            $this->assertItemIsValid($item, $index + 1);
        }

        $this->assertTotalsAreValid($invoice);
        $this->assertReferenceIsValid($invoice);
    }

    private function assertItemIsValid(InvoiceItem $item, int $row): void
    {
        if (preg_match('/^\d{13}$/', (string) $item->commodity_code) !== 1) {
            throw new MoadianConfigurationException("شناسه کالا/خدمت در ردیف {$row} باید دقیقاً ۱۳ رقم و در بانک شناسه‌های سازمان معتبر باشد.");
        }

        if (mb_strlen((string) $item->description) > 400) {
            throw new MoadianConfigurationException("شرح کالا/خدمت در ردیف {$row} نباید بیشتر از ۴۰۰ کاراکتر باشد.");
        }

        if ((float) $item->quantity <= 0) {
            throw new MoadianConfigurationException("تعداد یا مقدار ردیف {$row} باید بزرگ‌تر از صفر باشد.");
        }

        if ((float) $item->unit_price <= 0) {
            throw new MoadianConfigurationException("مبلغ واحد ردیف {$row} باید بزرگ‌تر از صفر باشد.");
        }

        if ((float) $item->discount < 0 || (float) $item->discount > (float) $item->subtotal) {
            throw new MoadianConfigurationException("تخفیف ردیف {$row} نمی‌تواند منفی یا بیشتر از مبلغ قبل از تخفیف باشد.");
        }

        if ((float) $item->tax_rate < 0 || (float) $item->tax_rate > 100) {
            throw new MoadianConfigurationException("نرخ مالیات ردیف {$row} معتبر نیست.");
        }

        $measurementUnitCode = $item->good?->measurement_unit_code;

        if ($measurementUnitCode !== null && preg_match('/^\d{1,8}$/', (string) $measurementUnitCode) !== 1) {
            throw new MoadianConfigurationException("کد واحد اندازه‌گیری ردیف {$row} باید از جدول رسمی واحدها و حداکثر ۸ رقم باشد.");
        }

        $calculatedSubtotal = round((float) $item->quantity * (float) $item->unit_price, 2);
        $calculatedTax = round(($calculatedSubtotal - (float) $item->discount) * (float) $item->tax_rate / 100, 2);
        $calculatedTotal = $calculatedSubtotal - (float) $item->discount + $calculatedTax;

        if (! $this->moneyEquals($calculatedSubtotal, $item->subtotal)
            || ! $this->moneyEquals($calculatedTax, $item->tax_amount)
            || ! $this->moneyEquals($calculatedTotal, $item->total)) {
            throw new MoadianConfigurationException("محاسبات مبلغ، تخفیف یا مالیات ردیف {$row} با قواعد سامانه مودیان تطابق ندارد؛ صورتحساب را دوباره ذخیره کنید.");
        }
    }

    private function assertTotalsAreValid(Invoice $invoice): void
    {
        $subtotal = (float) $invoice->items->sum(fn (InvoiceItem $item): float => (float) $item->subtotal);
        $discount = (float) $invoice->items->sum(fn (InvoiceItem $item): float => (float) $item->discount);
        $tax = (float) $invoice->items->sum(fn (InvoiceItem $item): float => (float) $item->tax_amount);
        $netAmount = $subtotal - $discount;

        if ($netAmount <= 0) {
            throw new MoadianConfigurationException('مجموع صورتحساب پس از تخفیف باید بزرگ‌تر از صفر باشد.');
        }

        if (! $this->moneyEquals($subtotal, $invoice->subtotal)
            || ! $this->moneyEquals($discount, $invoice->discount_total)
            || ! $this->moneyEquals($tax, $invoice->tax_total)
            || ! $this->moneyEquals($netAmount + $tax, $invoice->total)) {
            throw new MoadianConfigurationException('جمع مبالغ صورتحساب با جمع ردیف‌ها تطابق ندارد؛ صورتحساب را دوباره ذخیره کنید.');
        }

        if ($invoice->settlement_method === SettlementMethod::Mixed
            && ((float) $invoice->cash_amount <= 0 || (float) $invoice->cash_amount >= $netAmount)) {
            throw new MoadianConfigurationException('در تسویه نقدی/نسیه، مبلغ نقدی باید بزرگ‌تر از صفر و کمتر از مبلغ پس از تخفیف باشد.');
        }
    }

    private function assertReferenceIsValid(Invoice $invoice): void
    {
        if ($invoice->invoice_type === InvoiceType::Original) {
            return;
        }

        $reference = $invoice->referenceInvoice;

        if ($reference === null || $reference->user_id !== $invoice->user_id) {
            throw new MoadianConfigurationException('صورتحساب مرجع اصلاح یا ابطال معتبر نیست.');
        }

        if ($reference->invoice_type === InvoiceType::Cancellation) {
            throw new MoadianConfigurationException('صورتحساب ابطالی نمی‌تواند مرجع صورتحساب دیگری باشد.');
        }

        if ($reference->status !== InvoiceStatus::Confirmed || preg_match('/^[A-Z0-9]{22}$/i', (string) $reference->tax_id) !== 1) {
            throw new MoadianConfigurationException('مرجع اصلاح یا ابطال باید در مودیان تأیید شده و دارای شماره مالیاتی ۲۲ کاراکتری معتبر باشد.');
        }

        if ($invoice->invoice_date->isBefore($reference->invoice_date)) {
            throw new MoadianConfigurationException('تاریخ صورتحساب اصلاحی یا ابطالی نباید قبل از تاریخ صورتحساب مرجع باشد.');
        }

        if ($invoice->customer_id !== $reference->customer_id) {
            throw new MoadianConfigurationException('اطلاعات خریدار در صورتحساب اصلاحی قابل تغییر نیست.');
        }

        if ($invoice->invoice_type === InvoiceType::Correction) {
            $referenceGoodIds = $reference->items->pluck('good_id')->filter()->unique();
            $hasNewGood = $invoice->items->pluck('good_id')->filter()->unique()->diff($referenceGoodIds)->isNotEmpty();

            if ($hasNewGood) {
                throw new MoadianConfigurationException('افزودن شناسه کالا/خدمت جدید در صورتحساب اصلاحی مجاز نیست.');
            }
        }
    }

    private function moneyEquals(int|float|string|null $first, int|float|string|null $second): bool
    {
        return abs((float) $first - (float) $second) < 0.01;
    }
}
