<?php

namespace App\Services\Moadian;

use App\Exceptions\MoadianConfigurationException;
use App\Models\Invoice;
use App\Models\TaxpayerProfile;
use Illuminate\Support\Facades\DB;

class InvoiceSerialAllocator
{
    private const MAXIMUM_SERIAL = 0xFFFFFFFFFF;

    public function allocate(Invoice $invoice): int
    {
        return DB::transaction(function () use ($invoice): int {
            $profile = TaxpayerProfile::query()
                ->where('user_id', $invoice->user_id)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                throw new MoadianConfigurationException('پرونده مالیاتی کاربر برای تخصیص سریال صورتحساب یافت نشد.');
            }

            $maximumInvoiceId = (int) Invoice::query()
                ->where('user_id', $invoice->user_id)
                ->max('id');
            $maximumAssignedSerial = (int) Invoice::query()
                ->where('user_id', $invoice->user_id)
                ->max('moadian_serial');
            $serial = max(
                1,
                (int) $profile->next_invoice_serial,
                $maximumInvoiceId + 1,
                $maximumAssignedSerial + 1,
            );

            if ($serial > self::MAXIMUM_SERIAL) {
                throw new MoadianConfigurationException('ظرفیت سریال داخلی صورتحساب‌های این حافظه مالیاتی تکمیل شده است.');
            }

            $profile->next_invoice_serial = $serial + 1;
            $profile->save();

            return $serial;
        }, 3);
    }
}
