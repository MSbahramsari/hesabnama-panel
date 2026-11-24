<?php

namespace App\Services\Moadian;

use App\Enums\ElectronicInvoiceType;
use App\Enums\InvoiceType;
use App\Enums\SettlementMethod;
use App\Exceptions\MoadianConfigurationException;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\CarbonImmutable;

class InvoicePayloadFactory
{
    public function __construct(
        private TaxIdGenerator $taxIdGenerator,
        private InvoiceSubmissionValidator $submissionValidator,
        private RialInvoiceCalculator $calculator,
    ) {}

    /** @return array{header: array<string, mixed>, body: array<int, array<string, mixed>>, payments: array<int, array<string, mixed>>, extension: null} */
    public function make(Invoice $invoice, MoadianConfiguration $configuration): array
    {
        $configuration->assertReadyForSubmission();
        $invoice->loadMissing(['customer', 'items.good', 'referenceInvoice']);
        $this->submissionValidator->assertValid($invoice);

        $issuedAt = $this->issuedAt($invoice);
        $internalSerial = $invoice->moadian_serial ?? (int) $invoice->getKey();
        $taxId = $this->taxIdGenerator->generate($configuration->fiscalId(), $issuedAt, $internalSerial);
        $body = $invoice->items->map(fn (InvoiceItem $item): array => $this->body($item, $configuration))->all();
        $subtotal = array_sum(array_column($body, 'prdis'));
        $discount = array_sum(array_column($body, 'dis'));
        $tax = array_sum(array_column($body, 'vam'));
        $total = array_sum(array_column($body, 'tsstam'));
        $netAmount = $subtotal - $discount;
        $cashAmount = match ($invoice->settlement_method) {
            SettlementMethod::Cash => $netAmount,
            SettlementMethod::Credit => 0,
            SettlementMethod::Mixed => $this->money($invoice->cash_amount),
        };
        $creditAmount = max($netAmount - $cashAmount, 0);
        if ($invoice->settlement_method === SettlementMethod::Mixed && ($cashAmount <= 0 || $cashAmount >= $netAmount)) {
            throw new MoadianConfigurationException('مبلغ نقدی پس از قطع اعشار باید بزرگ‌تر از صفر و کمتر از مبلغ پس از تخفیف باشد.');
        }
        foreach ($body as &$row) {
            $row['cop'] = $this->calculator->paymentShare($row['tsstam'], (int) $cashAmount, $netAmount);
            $row['vop'] = $this->calculator->paymentShare($row['vam'], (int) $cashAmount, $netAmount);
        }
        unset($row);
        $vatPaid = array_sum(array_column($body, 'vop'));

        return [
            'header' => [
                'taxid' => $taxId,
                'indatim' => $issuedAt->getTimestampMs(),
                'indati2m' => null,
                'inty' => $invoice->electronic_invoice_type->value,
                'inno' => mb_strtoupper(str_pad(dechex($internalSerial), 10, '0', STR_PAD_LEFT)),
                'irtaxid' => $invoice->invoice_type === InvoiceType::Original ? null : $invoice->referenceInvoice?->tax_id,
                'inp' => 1,
                'ins' => $invoice->invoice_type->subjectCode(),
                'tins' => $configuration->sellerEconomicCode(),
                'tob' => $invoice->customer ? ($invoice->customer->type === 'individual' ? 1 : 2) : null,
                'bid' => $invoice->customer?->moadianNationalIdentifier(),
                'tinb' => $invoice->customer?->moadianEconomicCode(),
                'sbc' => $configuration->sellerBranchCode(),
                'bpc' => $invoice->customer?->postal_code,
                'bbc' => $invoice->customer?->branch_code,
                'ft' => null,
                'bpn' => null,
                'scln' => null,
                'scc' => null,
                'crn' => null,
                'billid' => null,
                'tprdis' => $subtotal,
                'tdis' => $discount,
                'tadis' => $netAmount,
                'tvam' => $tax,
                'todam' => 0,
                'tbill' => $total,
                'setm' => $invoice->settlement_method->moadianCode(),
                'cap' => $this->money($cashAmount),
                'insp' => $this->money($creditAmount),
                'tvop' => $this->money($vatPaid),
                'tax17' => 0,
            ],
            'body' => $body,
            'payments' => [$invoice->electronic_invoice_type === ElectronicInvoiceType::PosReceipt ? [
                'iinn' => $invoice->payment_switch_number,
                'acn' => $invoice->payment_acceptor_number,
                'trmn' => $invoice->payment_terminal_number,
                'pmt' => $invoice->payment_method,
                'trn' => $invoice->payment_tracking_number,
                'pcn' => $invoice->payer_card_number,
                'pid' => $invoice->payer_identifier,
                'pdt' => $invoice->payment_at?->getTimestampMs(),
                'pv' => $total,
            ] : [
                'iinn' => null, 'acn' => null, 'trmn' => null, 'pmt' => null, 'trn' => null,
                'pcn' => null, 'pid' => null, 'pdt' => null, 'pv' => null,
            ]],
            'extension' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function body(InvoiceItem $item, MoadianConfiguration $configuration): array
    {
        $measurementUnitCode = $item->good?->measurement_unit_code ?? $configuration->defaultMeasurementUnitCode();

        if ($measurementUnitCode === null) {
            throw new MoadianConfigurationException("کد واحد اندازه‌گیری رسمی برای قلم «{$item->description}» وارد نشده است.");
        }

        $amounts = $this->calculator->line($item->quantity, $item->unit_price, $item->tax_rate, $item->discount);
        $subtotal = $amounts['subtotal'];
        $discount = $amounts['discount'];
        $netAmount = $subtotal - $discount;
        $tax = $amounts['tax_amount'];

        return [
            'sstid' => $item->commodity_code,
            'sstt' => $item->description,
            'am' => $this->wireNumber($item->quantity),
            'mu' => $measurementUnitCode,
            'fee' => $this->wireNumber($item->unit_price),
            'cfee' => null,
            'cut' => null,
            'exr' => null,
            'prdis' => $subtotal,
            'dis' => $discount,
            'adis' => $netAmount,
            'vra' => (float) $item->tax_rate,
            'vam' => $tax,
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
            'cop' => 0,
            'vop' => 0,
            'bsrn' => null,
            'tsstam' => $netAmount + $tax,
        ];
    }

    private function issuedAt(Invoice $invoice): CarbonImmutable
    {
        $date = CarbonImmutable::parse($invoice->invoice_date->format('Y-m-d'), 'Asia/Tehran');
        $now = CarbonImmutable::now('Asia/Tehran');

        return $date->setTime($now->hour, $now->minute, $now->second, $now->microsecond);
    }

    private function money(int|float|string|null $value): int
    {
        return $this->calculator->rial($value);
    }

    private function wireNumber(string $value): int|float
    {
        if (bccomp($value, bcadd($value, '0', 0), 8) === 0) {
            return (int) $value;
        }

        $number = (float) $value;
        if (bccomp($value, sprintf('%.8F', $number), 8) !== 0) {
            throw new MoadianConfigurationException('دقت تعداد یا قیمت واحد با بزرگی مبلغ برای ارسال عددی قابل حفظ نیست؛ تعداد ارقام اعشار را کاهش دهید.');
        }

        return $number;
    }
}
