<?php

namespace App\Services\Spreadsheet;

use App\Enums\BuyerStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class MoadianSalesReportService
{
    public function __construct(private SpreadsheetFile $spreadsheet) {}

    /** @return array{updated: int, unmatched: int, ignored: int} */
    public function import(User $user, UploadedFile $file): array
    {
        $data = $this->spreadsheet->read($file);
        $taxIdHeader = $this->findHeader($data['headers'], [
            'شماره منحصر به فرد مالیاتی',
            'شماره منحصر بفرد مالیاتی',
            'شماره مالیاتی صورتحساب',
            'شماره مالیاتی',
            'شناسه یکتای صورتحساب مالیاتی',
            'شماره منحصر به فرد مالیاتی صورتحساب',
            'شماره منحصر بفرد مالیاتی صورتحساب',
            'taxid',
        ]);
        $buyerStatusHeader = $this->findHeader($data['headers'], [
            'وضعیت واکنش خریدار',
            'واکنش خریدار',
            'وضعیت واکنش',
            'وضعیت صورتحساب',
            'وضعیت در کارپوشه',
            'buyerstatus',
        ]);

        if ($taxIdHeader === null || $buyerStatusHeader === null) {
            throw new RuntimeException('ستون شماره مالیاتی یا وضعیت واکنش خریدار در فایل رسمی پیدا نشد. فایل خروجی «فروش داخلی» کارپوشه را بدون تغییر بارگذاری کنید.');
        }

        $updated = 0;
        $unmatched = 0;
        $ignored = 0;

        foreach ($data['rows'] as $row) {
            $taxId = mb_strtoupper(preg_replace('/\s+/u', '', $this->spreadsheet->normalizeDigits($row[$taxIdHeader] ?? '')) ?? '');
            $buyerStatus = $this->buyerStatus($row[$buyerStatusHeader] ?? '');

            if ($taxId === '' || $buyerStatus === null) {
                $ignored++;

                continue;
            }

            $invoice = Invoice::query()
                ->whereBelongsTo($user)
                ->where('tax_id', $taxId)
                ->first();

            if ($invoice === null) {
                $unmatched++;

                continue;
            }

            $invoice->update([
                'buyer_status' => $buyerStatus,
                'buyer_status_source' => 'moadian_sales_report',
                'buyer_status_updated_at' => now(),
            ]);
            $updated++;
        }

        return compact('updated', 'unmatched', 'ignored');
    }

    /** @param array<int, string> $headers
     * @param  array<int, string>  $aliases
     */
    private function findHeader(array $headers, array $aliases): ?string
    {
        $normalizedAliases = array_map($this->normalize(...), $aliases);

        foreach ($headers as $header) {
            if (in_array($this->normalize($header), $normalizedAliases, true)) {
                return $header;
            }
        }

        return null;
    }

    private function buyerStatus(mixed $value): ?BuyerStatus
    {
        $value = $this->normalize((string) $value);

        if (str_contains($value, 'عدمامکانواکنش') || $value === 'reactionimpossible') {
            return BuyerStatus::ReactionImpossible;
        }

        if (str_contains($value, 'عدمنیازبهواکنش') || $value === 'noreactionrequired') {
            return BuyerStatus::NoReactionRequired;
        }

        if (str_contains($value, 'تاییدسیستمی') || in_array($value, ['systemaccepted', 'systemconfirmed'], true)) {
            return BuyerStatus::SystemAccepted;
        }

        if (str_contains($value, 'درانتظارواکنش') || str_contains($value, 'درانتظارتایید') || $value === 'pending') {
            return BuyerStatus::Pending;
        }

        if (str_contains($value, 'ردشده') || str_contains($value, 'ردخریدار') || $value === 'rejected') {
            return BuyerStatus::Rejected;
        }

        if (str_contains($value, 'تاییدشده') || str_contains($value, 'تاییدخریدار') || in_array($value, ['accepted', 'confirmed'], true)) {
            return BuyerStatus::Accepted;
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = $this->spreadsheet->normalizeDigits($value);
        $value = strtr(mb_strtolower($value), [
            'ي' => 'ی',
            'ك' => 'ک',
            'أ' => 'ا',
            'إ' => 'ا',
            'آ' => 'ا',
        ]);

        return preg_replace('/[\s\x{200C}\x{200F}_\-\/]+/u', '', $value) ?? '';
    }
}
