<?php

namespace App\Console\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('stuff:import {files* : مسیر یک یا چند فایل CSV رسمی کالا و خدمت}')]
#[Description('Import official goods and services catalog CSV files into the local searchable catalog')]
class ImportStuffCatalog extends Command
{
    public function handle(): int
    {
        $totalImported = 0;
        $totalSkipped = 0;

        foreach ((array) $this->argument('files') as $file) {
            $path = $this->resolvePath((string) $file);

            if (! is_file($path) || ! is_readable($path)) {
                $this->components->error("فایل قابل خواندن نیست: {$path}");

                return self::FAILURE;
            }

            try {
                [$imported, $skipped] = $this->importFile($path);
            } catch (RuntimeException $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }

            $totalImported += $imported;
            $totalSkipped += $skipped;
            $this->components->info(basename($path).": {$imported} ردیف وارد یا به‌روزرسانی شد.");
        }

        $this->newLine();
        $this->line("مجموع ردیف‌های معتبر: {$totalImported}");
        $this->line("ردیف‌های نادیده‌گرفته‌شده: {$totalSkipped}");

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: int} */
    private function importFile(string $path): array
    {
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
            $imported = 0;
            $skipped = 0;
            $timestamp = now();

            while (($row = fgetcsv($handle, null, $delimiter, '"', '\\')) !== false) {
                $record = $this->makeRecord($row, $columnIndexes, $timestamp);

                if ($record === null) {
                    $skipped++;

                    continue;
                }

                $records[] = $record;

                if (count($records) >= 500) {
                    $this->upsert($records);
                    $imported += count($records);
                    $records = [];
                }
            }

            if ($records !== []) {
                $this->upsert($records);
                $imported += count($records);
            }

            return [$imported, $skipped];
        } finally {
            fclose($handle);
        }
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
            'description' => ['شرح', 'شرحشناسه', 'نامکالا', 'نامخدمت', 'نامکالاوخدمات', 'نامکالاوخدمت', 'description', 'title', 'name'],
            'type' => ['نوع', 'نوعشناسه', 'type'],
            'vat' => ['ارزشافزوده', 'نرخارزشافزوده', 'مالیات', 'نرخمالیات', 'vat', 'tax'],
            'source_created_date' => ['تاریخایجاد', 'createdat', 'createddate', 'date'],
            'effective_date' => ['تاریخاجرا', 'تاریخاجرایشناسه', 'rundate', 'effectivedate'],
            'expiration_date' => ['تاریخانقضا', 'انقضا', 'expirationdate', 'expiredate'],
            'source_updated_date' => ['تاریخبروزرسانی', 'تاریخبهروزرسانی', 'updatedat', 'updateddate'],
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
        $sourceCreatedDate = $this->latinDigits($value('source_created_date'));
        $effectiveDate = $this->latinDigits($value('effective_date'));
        $expirationDate = $this->latinDigits($value('expiration_date'));
        $sourceUpdatedDate = $this->latinDigits($value('source_updated_date'));
        $sourceHash = hash('sha256', implode('|', [$itemId, $description, $type, $effectiveDate, $expirationDate]));

        return [
            'item_id' => $itemId,
            'description' => $description,
            'type' => $type !== '' ? $type : null,
            'vat' => $vat,
            'source_created_date' => $sourceCreatedDate !== '' ? $sourceCreatedDate : null,
            'effective_date' => $effectiveDate !== '' ? $effectiveDate : null,
            'expiration_date' => $expirationDate !== '' ? $expirationDate : null,
            'source_updated_date' => $sourceUpdatedDate !== '' ? $sourceUpdatedDate : null,
            'source_hash' => $sourceHash,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /** @param array<int, array<string, mixed>> $records */
    private function upsert(array $records): void
    {
        DB::table('stuff_catalog_items')->upsert($records, ['source_hash'], [
            'description',
            'type',
            'vat',
            'source_created_date',
            'effective_date',
            'expiration_date',
            'source_updated_date',
            'updated_at',
        ]);
    }

    private function resolvePath(string $path): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) === 1) {
            return $path;
        }

        return base_path($path);
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
