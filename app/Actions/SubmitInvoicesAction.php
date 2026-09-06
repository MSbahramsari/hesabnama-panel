<?php

namespace App\Actions;

use App\Contracts\TaxPlatformGateway;
use App\Enums\InvoiceStatus;
use App\Enums\MoadianStatus;
use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class SubmitInvoicesAction
{
    public function __construct(private TaxPlatformGateway $gateway) {}

    /** @param Collection<int, Invoice> $invoices */
    public function handle(Collection $invoices): int
    {
        $submitted = 0;

        foreach ($invoices as $invoice) {
            $wasSubmitted = Cache::lock("invoice-submit:{$invoice->getKey()}", 60)->get(function () use ($invoice): bool {
                $invoice->refresh();

                if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::PendingSend, InvoiceStatus::MoadianError], true)) {
                    return false;
                }

                try {
                    $result = $this->gateway->submit($invoice);
                    $invoice->update([
                        'status' => InvoiceStatus::AwaitingConfirmation,
                        'moadian_status' => MoadianStatus::Pending,
                        'moadian_tax_result' => null,
                        'moadian_confirmation_reference_id' => null,
                        'moadian_packet_type' => null,
                        'submission_uid' => $result->uid,
                        'reference_number' => $result->referenceNumber,
                        'tax_id' => $result->taxId,
                        'sent_at' => now(),
                        'error_message' => null,
                    ]);

                    return true;
                } catch (MoadianConfigurationException|MoadianApiException $exception) {
                    $invoice->update([
                        'status' => InvoiceStatus::MoadianError,
                        'moadian_status' => MoadianStatus::Failed,
                        'error_message' => $exception->getMessage(),
                    ]);

                    return false;
                }
            });

            if ($wasSubmitted) {
                $submitted++;
            }
        }

        return $submitted;
    }
}
