<?php

namespace App\Services;

use App\Models\StuffCatalogItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StuffCatalogImporter
{
    private const UPDATE_COLUMNS = [
        'description',
        'type',
        'vat',
        'taxable',
        'source_created_date',
        'effective_date',
        'expiration_date',
        'source_updated_date',
    ];

    /**
     * @param  null|callable(int, int, int, StuffCatalogImportResult): void  $progress
     */
    public function import(string $path, ?callable $progress = null): StuffCatalogImportResult
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("فایل قابل خواندن نیست: {$path}");
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("باز کردن فایل ممکن نیست: {$path}");
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                throw new RuntimeException("فایل خالی است: {$path}");
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);
            $headers = fgetcsv($handle, null, $delimiter, '"', '\\');

            if (! is_array($headers)) {
                throw new RuntimeException("سطر عنوان فایل قابل پردازش نیست: {$path}");
            }

            $columnIndexes = $this->resolveColumnIndexes($headers);
            $records = [];
            $newRows = 0;
            $updatedRows = 0;
            $unchangedRows = 0;
            $skippedRows = 0;
            $processedRows = 0;
            $totalBytes = max(1, (int) (filesize($path) ?: 1));
            $timestamp = now();

            while (($row = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
                $processedRows++;
                $record = $this->makeRecord($row, $columnIndexes, $timestamp);

                if ($record === null) {
                    $skippedRows++;

                    if ($processedRows % 500 === 0) {
                        $this->reportProgress($progress, $handle, $totalBytes, $processedRows, $newRows, $updatedRows, $unchangedRows, $skippedRows);
                    }

                    continue;
                }

                $records[] = $record;

                if (count($records) >= 500) {
                    [$new, $updated, $unchanged] = $this->importBatch($records);
                    $newRows += $new;
                    $updatedRows += $updated;
                    $unchangedRows += $unchanged;
                    $records = [];
                }

                if ($processedRows % 500 === 0) {
                    $this->reportProgress($progress, $handle, $totalBytes, $processedRows, $newRows, $updatedRows, $unchangedRows, $skippedRows);
                }
            }

            if ($records !== []) {
                [$new, $updated, $unchanged] = $this->importBatch($records);
                $newRows += $new;
                $updatedRows += $updated;
                $unchangedRows += $unchanged;
            }

            $this->reportProgress($progress, $handle, $totalBytes, $processedRows, $newRows, $updatedRows, $unchangedRows, $skippedRows);

            return new StuffCatalogImportResult($newRows, $updatedRows, $unchangedRows, $skippedRows);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  null|callable(int, int, int, StuffCatalogImportResult): void  $progress
     */
    private function reportProgress(
        ?callable $progress,
        $handle,
        int $totalBytes,
        int $processedRows,
        int $newRows,
        int $updatedRows,
        int $unchangedRows,
        int $skippedRows,
    ): void {
        if ($progress === null) {
            return;
        }

        $processedBytes = max(0, (int) ftell($handle));
        $progress(
            $processedBytes,
            $totalBytes,
            $processedRows,
            new StuffCatalogImportResult($newRows, $updatedRows, $unchangedRows, $skippedRows),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{0: int, 1: int, 2: int}
     */
    private function importBatch(array $records): array
    {
        $existingItems = StuffCatalogItem::query()
            ->whereIn('source_hash', array_column($records, 'source_hash'))
            ->get()
            ->keyBy('source_hash');
        $changedRecords = [];
        $newRows = 0;
        $updatedRows = 0;
        $unchangedRows = 0;

        foreach ($records as $record) {
            $existingItem = $existingItems->get($record['source_hash']);

            if ($existingItem === null) {
                $newRows++;
                $changedRecords[] = $record;

                continue;
            }

            if ($this->hasChanges($existingItem, $record)) {
                $updatedRows++;
                $changedRecords[] = $record;
            } else {
                $unchangedRows++;
            }
        }

        if ($changedRecords !== []) {
            DB::transaction(fn () => DB::table('stuff_catalog_items')->upsert(
                $changedRecords,
                ['source_hash'],
                [...self::UPDATE_COLUMNS, 'updated_at'],
            ));
        }

        return [$newRows, $updatedRows, $unchangedRows];
    }

    /** @param array<string, mixed> $record */
    private function hasChanges(StuffCatalogItem $item, array $record): bool
    {
        foreach (self::UPDATE_COLUMNS as $column) {
            if ($column === 'vat') {
                if ((float) $item->{$column} !== (float) $record[$column]) {
                    return true;
                }

                continue;
            }

            if ((string) ($item->{$column} ?? '') !== (string) ($record[$column] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<string, int|null>
     */
    private function resolveColumnIndexes(array $headers): array
    {
        $normalizedHeaders = array_map(fn (?string $header): string => $this->normalizeHeader((string) $header), $headers);
        $aliases = [
            'item_id' => ['شناسه', 'کدشناسه', 'شناسهکالا', 'شناسهخدمت', 'شناسهکالاوخدمت', 'itemid', 'id', 'code'],
            'description' => ['شرح', 'شرحشناسه', 'نامکالا', 'نامخدمت', 'نامکالاوخدمات', 'نامکالاوخدمت', 'descriptionofid', 'description', 'title', 'name'],
            'type' => ['نوع', 'نوعشناسه', 'type'],
            'vat' => ['ارزشافزوده', 'نرخارزشافزوده', 'مالیات', 'نرخمالیات', 'vat', 'tax'],
            'taxable' => ['وضعیتمشمولیامعافبودنشناسهکالاوخدمت', 'وضعیتمشمولیت', 'مشمولیت', 'taxable'],
            'source_created_date' => ['تاریخایجاد', 'createdat', 'createdate', 'createddate', 'date'],
            'effective_date' => ['تاریخاجرا', 'تاریخاجرایشناسه', 'rundate', 'effectivedate'],
            'expiration_date' => ['تاریخانقضا', 'انقضا', 'expirationdate', 'expiredate'],
            'source_updated_date' => ['تاریخبروزرسانی', 'تاریخبهروزرسانی', 'lasteditdate', 'updatedat', 'updateddate'],
        ];
        $indexes = [];

        foreach ($aliases as $field => $fieldAliases) {
            $index = null;

            foreach ($fieldAliases as $alias) {
                $found = array_search($this->normalizeHeader($alias), $normalizedHeaders, true);

                if ($found !== false) {
                    $index = $found;

                    break;
                }
            }

            $indexes[$field] = $index;
        }

        if ($indexes['item_id'] === null || $indexes['description'] === null) {
            throw new RuntimeException('ستون‌های «شناسه» و «شرح/نام کالا و خدمت» در فایل پیدا نشد.');
        }

        return $indexes;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int|null>  $indexes
     * @return array<string, mixed>|null
     */
    private function makeRecord(array $row, array $indexes, CarbonInterface $timestamp): ?array
    {
        $value = function (string $field) use ($row, $indexes): string {
            $index = $indexes[$field] ?? null;

            return $index === null ? '' : $this->toUtf8(trim((string) ($row[$index] ?? '')));
        };
        $itemId = preg_replace('/\D+/', '', $this->latinDigits($value('item_id'))) ?? '';
        $description = $this->normalizeText($value('description'));

        if (! preg_match('/^\d{8,20}$/', $itemId) || $description === '') {
            return null;
        }

        $type = $this->normalizeText($value('type'));
        $vat = $this->vat($value('vat'));
        $taxable = $this->normalizeText($value('taxable'));
        $sourceCreatedDate = $this->latinDigits($value('source_created_date'));
        $effectiveDate = $this->latinDigits($value('effective_date'));
        $expirationDate = $this->latinDigits($value('expiration_date'));
        $sourceUpdatedDate = $this->latinDigits($value('source_updated_date'));
        $sourceHash = hash('sha256', implode('|', [$itemId, $effectiveDate, $expirationDate]));

        return [
            'item_id' => $itemId,
            'description' => $description,
            'type' => $type !== '' ? $type : null,
            'vat' => $vat,
            'taxable' => $taxable !== '' ? $taxable : null,
            'source_created_date' => $sourceCreatedDate !== '' ? $sourceCreatedDate : null,
            'effective_date' => $effectiveDate !== '' ? $effectiveDate : null,
            'expiration_date' => $expirationDate !== '' ? $expirationDate : null,
            'source_updated_date' => $sourceUpdatedDate !== '' ? $sourceUpdatedDate : null,
            'source_hash' => $sourceHash,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function detectDelimiter(string $headerLine): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = count(str_getcsv($headerLine, $delimiter, '"', '\\'));
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower($this->toUtf8($header));
        $header = str_replace(["\xEF\xBB\xBF", 'ي', 'ك'], ['', 'ی', 'ک'], $header);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $header) ?? '';
    }

    private function normalizeText(string $value): string
    {
        return str_replace(['ي', 'ك'], ['ی', 'ک'], $this->toUtf8($value));
    }

    private function toUtf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1256');
    }

    private function latinDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private function vat(string $value): float
    {
        $value = str_replace(['%', '٪', ',', ' '], ['', '', '.', ''], $this->latinDigits($value));

        return is_numeric($value) ? max(0, min(100, (float) $value)) : 0;
    }
}
