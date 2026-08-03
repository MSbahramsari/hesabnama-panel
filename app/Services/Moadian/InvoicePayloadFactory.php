<?php

namespace App\Services\Moadian;

use App\Exceptions\MoadianConfigurationException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\CarbonImmutable;

class InvoicePayloadFactory
{
    public function __construct(
        private MoadianConfiguration $configuration,
        private TaxIdGenerator $taxIdGenerator,
    ) {}

    /** @return array{header: array<string, mixed>, body: array<int, array<string, mixed>>, payments: array<int, array<string, mixed>>, extension: null} */
    public function make(Invoice $invoice): array
    {
        $this->configuration->assertReadyForSubmission();
        $invoice->loadMissing(['customer', 'items.good']);

        if ($invoice->items->isEmpty()) {
            throw new MoadianConfigurationException('صورتحساب بدون قلم قابل ارسال به سامانه مودیان نیست.');
        }

        $issuedAt = $this->issuedAt($invoice);
        $taxId = $this->taxIdGenerator->generate($this->configuration->fiscalId(), $issuedAt, (int) $invoice->getKey());

        return [
            'header' => [
                'taxid' => $taxId,
                'indatim' => $issuedAt->getTimestampMs(),
                'indati2m' => $issuedAt->getTimestampMs(),
                'inty' => 1,
                'inno' => mb_strtoupper(str_pad(dechex((int) $invoice->getKey()), 10, '0', STR_PAD_LEFT)),
                'irtaxid' => null,
                'inp' => 1,
                'ins' => 1,
                'tins' => $this->configuration->sellerEconomicCode(),
                'tob' => $invoice->customer->type === 'individual' ? 1 : 2,
                'bid' => $invoice->customer->national_id,
                'tinb' => $invoice->customer->economic_code,
                'sbc' => $this->configuration->sellerBranchCode(),
                'bpc' => $invoice->customer->postal_code,
                'bbc' => null,
                'ft' => null,
                'bpn' => null,
                'scln' => null,
                'scc' => null,
                'crn' => null,
                'billid' => null,
                'tprdis' => $this->money($invoice->subtotal),
                'tdis' => $this->money($invoice->discount_total),
                'tadis' => $this->money((float) $invoice->subtotal - (float) $invoice->discount_total),
                'tvam' => $this->money($invoice->tax_total),
                'todam' => 0,
                'tbill' => $this->money($invoice->total),
                'setm' => null,
                'cap' => null,
                'insp' => null,
                'tvop' => null,
                'tax17' => 0,
            ],
            'body' => $invoice->items->map(fn (InvoiceItem $item): array => $this->body($item))->all(),
            'payments' => [[
                'iinn' => null,
                'acn' => null,
                'trmn' => null,
                'trn' => null,
                'pcn' => null,
                'pid' => null,
                'pdt' => null,
            ]],
            'extension' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function body(InvoiceItem $item): array
    {
        $measurementUnitCode = $item->good?->measurement_unit_code ?? $this->configuration->defaultMeasurementUnitCode();

        if ($measurementUnitCode === null) {
            throw new MoadianConfigurationException("کد واحد اندازه‌گیری رسمی برای قلم «{$item->description}» وارد نشده است.");
        }

        return [
            'sstid' => $item->commodity_code,
            'sstt' => $item->description,
            'am' => (float) $item->quantity,
            'mu' => $measurementUnitCode,
            'fee' => $this->money($item->unit_price),
            'cfee' => null,
            'cut' => null,
            'exr' => null,
            'prdis' => $this->money($item->subtotal),
            'dis' => $this->money($item->discount),
            'adis' => $this->money((float) $item->subtotal - (float) $item->discount),
            'vra' => (float) $item->tax_rate,
            'vam' => $this->money($item->tax_amount),
            'odt' => null,
            'odr' => null,
            'odam' => null,
            'olt' => null,
            'olr' => null,
            'olam' => null,
            'consfee' => null,
            'spro' => null,
            'bros' => null,
            'tcpbs' => null,
            'cop' => null,
            'vop' => null,
            'bsrn' => null,
            'tsstam' => $this->money($item->total),
        ];
    }

    private function issuedAt(Invoice $invoice): CarbonImmutable
    {
        $date = CarbonImmutable::parse($invoice->invoice_date->format('Y-m-d'), 'Asia/Tehran');

        return $date->isToday() ? CarbonImmutable::now('Asia/Tehran') : $date->startOfDay();
    }

    private function money(int|float|string|null $value): int
    {
        return (int) round((float) $value);
    }
}
