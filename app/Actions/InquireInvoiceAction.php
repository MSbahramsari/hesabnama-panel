<?php

namespace App\Actions;

use App\Contracts\TaxPlatformGateway;
use App\Enums\InvoiceStatus;
use App\Enums\MoadianStatus;
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
            'moadian_tax_result' => $result->taxResult,
            'moadian_confirmation_reference_id' => $result->confirmationReferenceId,
            'moadian_packet_type' => $result->packetType,
        ];

        if ($result->isSuccessful()) {
            $attributes['status'] = InvoiceStatus::Confirmed;
            $attributes['moadian_status'] = MoadianStatus::Success;
            $attributes['confirmed_at'] = now();
        } elseif ($result->isFailed()) {
            $attributes['status'] = InvoiceStatus::MoadianError;
            $attributes['moadian_status'] = MoadianStatus::Failed;
            $attributes['error_message'] = $result->taxResult ?? 'سامانه مودیان ارسال صورتحساب را ناموفق اعلام کرد.';
        } else {
            $attributes['status'] = InvoiceStatus::AwaitingConfirmation;
            $attributes['moadian_status'] = MoadianStatus::Pending;
        }

        $invoice->update($attributes);
    }
}
