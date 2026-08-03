<?php

namespace App\Actions;

use App\Contracts\TaxPlatformGateway;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;

class InquireInvoiceAction
{
    public function __construct(private TaxPlatformGateway $gateway) {}

    public function handle(Invoice $invoice): void
    {
        $result = $this->gateway->inquire($invoice);
        $attributes = [
            'last_inquired_at' => now(),
            'error_message' => null,
        ];

        if ($result->isSuccessful()) {
            $attributes['status'] = InvoiceStatus::Confirmed;
            $attributes['confirmed_at'] = now();
        } elseif ($result->isFailed()) {
            $attributes['status'] = InvoiceStatus::MoadianError;
            $attributes['error_message'] = $result->taxResult ?? 'سامانه مودیان ارسال صورتحساب را ناموفق اعلام کرد.';
        }

        $invoice->update($attributes);
    }
}
