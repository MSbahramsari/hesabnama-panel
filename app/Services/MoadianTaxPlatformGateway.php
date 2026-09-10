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
use Illuminate\Support\Arr;
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

        $type = $this->customerType($this->customerValue($customer, ['taxpayerType', 'personType', 'type']));
        $nationalId = (string) ($this->customerValue($customer, ['nationalId', 'nationalCode', 'nid']) ?? '');

        if ($nationalId === '' && in_array(strlen($economicCode), [10, 11], true)) {
            $nationalId = $economicCode;
        }

        return [
            'name' => (string) ($this->customerValue($customer, ['nameTrade', 'taxpayerName', 'name', 'title']) ?? $economicCode),
            'national_id' => $nationalId,
            'type' => $type,
            'address' => (string) ($this->customerValue($customer, ['addressTaxpayer', 'taxpayerAddress', 'fullAddress', 'address']) ?? ''),
            'postal_code' => (string) ($this->customerValue($customer, ['postalcodeTaxpayer', 'postalCodeTaxpayer', 'postalCode', 'postalcode']) ?? ''),
            'phone' => (string) ($this->customerValue($customer, ['phoneTaxpayer', 'taxpayerPhone', 'phoneNumber', 'phone', 'tel']) ?? ''),
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

        return in_array($type, ['1', 'NATURAL', 'REAL', 'حقیقی', 'طبیعی'], true) ? 'individual' : 'legal';
    }

    /**
     * @param  array<string, mixed>  $customer
     * @param  array<int, string>  $aliases
     */
    private function customerValue(array $customer, array $aliases): mixed
    {
        $normalizedAliases = collect($aliases)
            ->map(fn (string $alias): string => mb_strtolower($alias))
            ->all();

        foreach (Arr::dot($customer) as $key => $value) {
            $fields = array_map(
                fn (string $field): string => mb_strtolower($field),
                explode('.', $key),
            );

            if (array_intersect($fields, $normalizedAliases) !== [] && filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
