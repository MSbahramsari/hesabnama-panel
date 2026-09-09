<?php

namespace App\Services;

use App\Contracts\TaxPlatformGateway;
use App\Exceptions\MoadianApiException;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\InquiryResult;
use App\Services\Moadian\InvoicePayloadFactory;
use App\Services\Moadian\InvoiceSerialAllocator;
use App\Services\Moadian\MoadianClientFactory;
use App\Services\Moadian\SubmissionResult;
use Illuminate\Support\Str;

class MoadianTaxPlatformGateway implements TaxPlatformGateway
{
    public function __construct(
        private MoadianClientFactory $clientFactory,
        private InvoicePayloadFactory $payloadFactory,
        private InvoiceSerialAllocator $serialAllocator,
    ) {}

    public function lookupCustomer(User $user, string $economicCode): ?array
    {
        $customer = $this->clientFactory->forUser($user)->economicCodeInformation($economicCode);

        if ($customer === null) {
            return null;
        }

        return [
            'name' => (string) ($customer['nameTrade'] ?? $customer['taxpayerName'] ?? $economicCode),
            'national_id' => (string) ($customer['nationalId'] ?? ''),
            'type' => $this->customerType($customer['taxpayerType'] ?? null),
            'address' => (string) ($customer['addressTaxpayer'] ?? ''),
            'postal_code' => (string) ($customer['postalcodeTaxpayer'] ?? ''),
        ];
    }

    public function lookupGood(User $user, string $commodityCode): ?array
    {
        $good = $this->clientFactory->forUser($user)->serviceStuffInformation($commodityCode);

        if ($good === null) {
            return null;
        }

        $itemId = (string) ($good['itemId'] ?? $commodityCode);

        return [
            'name' => (string) ($good['descriptionOfId'] ?? $good['description'] ?? $good['itemTitle'] ?? $good['title'] ?? $good['name'] ?? $commodityCode),
            'unit' => (string) ($good['unitTitle'] ?? $good['measurementUnit'] ?? (str_starts_with($itemId, '233') ? 'خدمت' : 'عدد')),
            'tax_rate' => (int) round((float) ($good['tax'] ?? $good['vat'] ?? $good['taxRate'] ?? 0)),
            'measurement_unit_code' => (string) ($good['unitCode'] ?? ''),
        ];
    }

    public function submit(Invoice $invoice): SubmissionResult
    {
        $configuration = $this->clientFactory->configurationForUser($invoice->user);
        $client = $this->clientFactory->forUser($invoice->user);
        $isRetry = filled($invoice->submission_uid) && blank($invoice->reference_number);
        $uid = $invoice->submission_uid ?? (string) Str::uuid();

        if (! $isRetry) {
            $uid = (string) Str::uuid();
            $serial = $this->serialAllocator->allocate($invoice);
            $invoice->update([
                'submission_uid' => $uid,
                'moadian_serial' => $serial,
                'tax_id' => null,
                'reference_number' => null,
                'moadian_tax_result' => null,
                'moadian_confirmation_reference_id' => null,
                'moadian_packet_type' => null,
                'last_inquired_at' => null,
            ]);
        }

        $invoice->refresh();
        $payload = $this->payloadFactory->make($invoice, $configuration);
        $invoice->update(['tax_id' => $payload['header']['taxid']]);

        return $client->submitInvoice($payload, $uid, $isRetry);
    }

    public function inquire(Invoice $invoice): InquiryResult
    {
        $client = $this->clientFactory->forUser($invoice->user);

        if (filled($invoice->reference_number)) {
            return $client->inquiryByReferenceNumber((string) $invoice->reference_number);
        }

        if (filled($invoice->submission_uid)) {
            return $client->inquiryByUid((string) $invoice->submission_uid);
        }

        throw new MoadianApiException('برای استعلام این صورتحساب، شناسه ارسال یا شماره مرجع مودیان ثبت نشده است.');
    }

    public function isDemo(): bool
    {
        return false;
    }

    private function customerType(mixed $taxpayerType): string
    {
        $type = mb_strtoupper((string) $taxpayerType);

        return in_array($type, ['1', 'NATURAL', 'REAL'], true) ? 'individual' : 'legal';
    }
}
