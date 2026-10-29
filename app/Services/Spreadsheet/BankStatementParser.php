<?php

namespace App\Services\Spreadsheet;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class BankStatementParser
{
    private const MAX_ROWS = 20000;

    /** @var array<string, array<int, string>> */
    private const HEADER_ALIASES = [
        'date' => ['تاریخ', 'تاريخ'],
        'description' => ['شرح سند', 'توضیحات', 'توضيحات', 'شرح', 'عملیات', 'عمليات'],
        'debit' => ['مبلغ گردش بدهکار', 'برداشت', 'بدهکار'],
        'credit' => ['مبلغ گردش بستانکار', 'واریز', 'واريز', 'بستانکار'],
        'amount' => ['مبلغ تراکنش', 'مبلغ گردش', 'مبلغ'],
        'type' => ['نوع تراکنش', 'نوع گردش'],
        'tracking' => ['شماره پیگیری', 'شماره پيگيري', 'کد پیگیری', 'پیگیری', 'شماره سند', 'شماره سریال', 'سند'],
    ];

    public function parse(UploadedFile $file): BankStatementConversion
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
        } catch (Throwable $exception) {
            throw new RuntimeException('فایل صورتحساب بانکی معتبر نیست یا قابل خواندن نیست.', previous: $exception);
        }

        try {
            $worksheet = $spreadsheet->getActiveSheet();
            $columns = $this->locateColumns($worksheet);
            $highestRow = min($worksheet->getHighestDataRow(), self::MAX_ROWS + $columns['header_row'] + 1);
            $transactions = [];
            $sourceRows = 0;
            $skippedRows = 0;

            for ($row = $columns['header_row'] + 1; $row <= $highestRow; $row++) {
                $sourceRows++;
                $transaction = $this->transactionFromRow($worksheet, $row, $columns);

                if ($transaction === null) {
                    $skippedRows++;

                    continue;
                }

                $transactions[] = $transaction;

                if (count($transactions) > self::MAX_ROWS) {
                    throw new RuntimeException('حداکثر ۲۰٬۰۰۰ گردش بانکی در هر فایل قابل تبدیل است.');
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        if ($transactions === []) {
            throw new RuntimeException('هیچ گردش بانکی قابل‌تبدیلی در فایل پیدا نشد. ستون‌های تاریخ، شرح و مبلغ را بررسی کنید.');
        }

        return new BankStatementConversion(
            detectedBank: $this->detectBank($file->getClientOriginalName()),
            transactions: $transactions,
            sourceRows: $sourceRows,
            skippedRows: $skippedRows,
        );
    }

    /**
     * @return array{header_row: int, date: int, description: int, debit: ?int, credit: ?int, amount: ?int, type: ?int, tracking: ?int}
     */
    private function locateColumns(Worksheet $worksheet): array
    {
        $highestColumn = min(Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()), 100);
        $highestRow = min($worksheet->getHighestDataRow(), 60);
        $best = null;
        $bestScore = -1;

        for ($row = 1; $row <= $highestRow; $row++) {
            $headers = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $headers[$column] = $this->normalizeText($worksheet->getCell([$column, $row])->getFormattedValue());
            }

            $candidate = [
                'header_row' => $row,
                'date' => $this->findColumn($headers, self::HEADER_ALIASES['date']),
                'description' => $this->findColumn($headers, self::HEADER_ALIASES['description']),
                'debit' => $this->findColumn($headers, self::HEADER_ALIASES['debit']),
                'credit' => $this->findColumn($headers, self::HEADER_ALIASES['credit']),
                'amount' => $this->findColumn($headers, self::HEADER_ALIASES['amount']),
                'type' => $this->findColumn($headers, self::HEADER_ALIASES['type']),
                'tracking' => $this->findColumn($headers, self::HEADER_ALIASES['tracking']),
            ];
            $hasSplitAmounts = $candidate['debit'] !== null || $candidate['credit'] !== null;
            $hasTypedAmount = $candidate['amount'] !== null && $candidate['type'] !== null;
            $score = collect($candidate)->except('header_row')->filter(fn (?int $value): bool => $value !== null)->count();

            if ($candidate['date'] !== null && $candidate['description'] !== null && ($hasSplitAmounts || $hasTypedAmount) && $score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        if (! is_array($best)) {
            throw new RuntimeException('ساختار صورتحساب بانکی شناسایی نشد. فایل باید دارای ستون‌های تاریخ، شرح و برداشت/واریز باشد.');
        }

        return $best;
    }

    /**
     * @param  array{header_row: int, date: int, description: int, debit: ?int, credit: ?int, amount: ?int, type: ?int, tracking: ?int}  $columns
     * @return array{date: string, description: string, debit: float, credit: float, tracking_number: string}|null
     */
    private function transactionFromRow(Worksheet $worksheet, int $row, array $columns): ?array
    {
        $date = $this->dateValue($worksheet, $columns['date'], $row);

        if ($date === null) {
            return null;
        }

        $description = $this->cellText($worksheet, $columns['description'], $row);
        $trackingNumber = $columns['tracking'] === null ? '' : $this->cellText($worksheet, $columns['tracking'], $row);
        $debit = $columns['debit'] === null ? 0.0 : $this->amountValue($worksheet->getCell([$columns['debit'], $row])->getValue());
        $credit = $columns['credit'] === null ? 0.0 : $this->amountValue($worksheet->getCell([$columns['credit'], $row])->getValue());

        if ($columns['amount'] !== null && $columns['type'] !== null && $debit === 0.0 && $credit === 0.0) {
            $amount = $this->amountValue($worksheet->getCell([$columns['amount'], $row])->getValue());
            $type = $this->normalizeText($this->cellText($worksheet, $columns['type'], $row));

            if (str_contains($type, 'برداشت') || str_contains($type, 'بدهکار')) {
                $debit = $amount;
            } elseif (str_contains($type, 'واریز') || str_contains($type, 'بستانکار')) {
                $credit = $amount;
            }
        }

        if (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) {
            return null;
        }

        return [
            'date' => $date,
            'description' => $description !== '' ? $description : 'گردش بانکی',
            'debit' => $debit,
            'credit' => $credit,
            'tracking_number' => $trackingNumber,
        ];
    }

    /** @param array<int, string> $headers */
    private function findColumn(array $headers, array $aliases): ?int
    {
        $normalizedAliases = array_map($this->normalizeText(...), $aliases);

        foreach ($headers as $column => $header) {
            if ($header !== '' && in_array($header, $normalizedAliases, true)) {
                return $column;
            }
        }

        foreach ($headers as $column => $header) {
            foreach ($normalizedAliases as $alias) {
                if ($header !== '' && mb_strlen($alias) >= 4 && str_contains($header, $alias)) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function dateValue(Worksheet $worksheet, int $column, int $row): ?string
    {
        $cell = $worksheet->getCell([$column, $row]);
        $raw = $cell->getValue();

        if (is_numeric($raw) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y/m/d');
        }

        $value = $this->normalizeDigits($cell->getFormattedValue());
        $value = preg_replace('/[^0-9\/\-]/u', '', $value) ?? '';
        $value = str_replace('-', '/', $value);

        if (! preg_match('/^(\d{2,4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
            return null;
        }

        return sprintf('%s/%02d/%02d', $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    private function amountValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return max((float) $value, 0);
        }

        $normalized = $this->normalizeDigits((string) $value);
        $normalized = str_replace(['٬', ',', '،', ' ', "\u{00A0}"], '', $normalized);
        $normalized = preg_replace('/\/0+$/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? '';

        return is_numeric($normalized) ? max((float) $normalized, 0) : 0.0;
    }

    private function cellText(Worksheet $worksheet, int $column, int $row): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->normalizeDigits($worksheet->getCell([$column, $row])->getFormattedValue())) ?? '');
    }

    private function normalizeText(mixed $value): string
    {
        $value = strtr(trim((string) $value), ['ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', "\u{200C}" => ' ', "\u{200F}" => '', "\u{202A}" => '', "\u{202C}" => '']);

        return mb_strtolower(preg_replace('/[\s_\-()（）:：\/]+/u', ' ', $value) ?? '');
    }

    private function normalizeDigits(mixed $value): string
    {
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function detectBank(string $filename): string
    {
        foreach (['پارسیان', 'پاسارگاد', 'تجارت', 'رسالت', 'رفاه', 'سپه', 'شهر', 'کشاورزی', 'ملت', 'ملی', 'مهر'] as $bank) {
            if (str_contains($filename, $bank)) {
                return 'بانک '.$bank;
            }
        }

        return 'بانک شناسایی‌نشده';
    }
}
