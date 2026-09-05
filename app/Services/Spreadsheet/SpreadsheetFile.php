<?php

namespace App\Services\Spreadsheet;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use XMLReader;
use ZipArchive;

class SpreadsheetFile
{
    private const MAX_ROWS = 20000;

    /** @return array{headers: array<int, string>, rows: array<int, array<string, string|int|float|null>>} */
    public function read(UploadedFile $file): array
    {
        $extension = mb_strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'xlsx' => $this->readXlsx($file->getRealPath()),
            'csv' => $this->readCsv($file->getRealPath()),
            default => throw new RuntimeException('فرمت فایل باید xlsx یا csv باشد.'),
        };
    }

    /** @param iterable<int, array<int, string|int|float|null>> $rows */
    public function download(string $filename, array $headers, iterable $rows): BinaryFileResponse
    {
        $directory = storage_path('app/private/spreadsheet-exports');

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('ساخت مسیر موقت خروجی ممکن نیست.');
        }

        $path = tempnam($directory, 'xlsx-');

        if ($path === false) {
            throw new RuntimeException('ساخت فایل موقت خروجی ممکن نیست.');
        }

        $this->writeXlsx($path, $headers, $rows);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function normalizeDigits(string|int|float|null $value): string
    {
        return strtr(trim((string) $value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<string, string|int|float|null>>} */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('خواندن فایل CSV ممکن نیست.');
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = $this->detectDelimiter($firstLine);
        $rawRows = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rawRows[] = array_map(fn (mixed $value): string => trim((string) $value), $row);

            if (count($rawRows) > self::MAX_ROWS + 1) {
                fclose($handle);
                throw new RuntimeException('حداکثر ۲۰٬۰۰۰ ردیف در هر فایل قابل پردازش است.');
            }
        }

        fclose($handle);

        return $this->mapRowsToHeaders($rawRows);
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<string, string|int|float|null>>} */
    private function readXlsx(string $path): array
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException('فایل اکسل معتبر نیست یا قابل بازشدن نیست.');
        }

        $uncompressedSize = 0;

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $uncompressedSize += (int) (($archive->statIndex($index)['size'] ?? 0));

            if ($uncompressedSize > 100 * 1024 * 1024) {
                $archive->close();
                throw new RuntimeException('حجم بازشده فایل اکسل بیش از حد مجاز است.');
            }
        }

        $sharedStrings = $this->sharedStrings($archive);
        $sheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');

        if ($sheetXml === false) {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->getNameIndex($index);

                if (is_string($entry) && str_starts_with($entry, 'xl/worksheets/sheet') && str_ends_with($entry, '.xml')) {
                    $sheetXml = $archive->getFromIndex($index);
                    break;
                }
            }
        }

        $archive->close();

        if (! is_string($sheetXml)) {
            throw new RuntimeException('برگه‌ای در فایل اکسل پیدا نشد.');
        }

        $reader = new XMLReader;
        $reader->XML($sheetXml, null, LIBXML_NONET | LIBXML_COMPACT);
        $rawRows = [];

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            $rowXml = simplexml_load_string($reader->readOuterXml(), \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

            if ($rowXml === false) {
                continue;
            }

            $row = [];

            foreach ($rowXml->c as $cell) {
                $reference = (string) $cell['r'];
                $column = $this->columnIndex($reference);
                $type = (string) $cell['t'];
                $value = match ($type) {
                    's' => $sharedStrings[(int) $cell->v] ?? '',
                    'inlineStr' => $this->inlineString($cell),
                    'b' => (string) ((int) $cell->v),
                    default => isset($cell->v) ? (string) $cell->v : '',
                };
                $row[$column] = $value;
            }

            if ($row !== []) {
                $maxColumn = max(array_keys($row));
                $rawRows[] = array_replace(array_fill(0, $maxColumn + 1, ''), $row);
            }

            if (count($rawRows) > self::MAX_ROWS + 1) {
                $reader->close();
                throw new RuntimeException('حداکثر ۲۰٬۰۰۰ ردیف در هر فایل قابل پردازش است.');
            }
        }

        $reader->close();

        return $this->mapRowsToHeaders($rawRows);
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $archive): array
    {
        $xml = $archive->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

        if ($document === false) {
            return [];
        }

        $strings = [];

        foreach ($document->si as $item) {
            $parts = $item->xpath('.//*[local-name()="t"]') ?: [];
            $strings[] = implode('', array_map(fn ($part): string => (string) $part, $parts));
        }

        return $strings;
    }

    private function inlineString(\SimpleXMLElement $cell): string
    {
        $parts = $cell->xpath('.//*[local-name()="t"]') ?: [];

        return implode('', array_map(fn ($part): string => (string) $part, $parts));
    }

    /** @param array<int, array<int, string|int|float|null>> $rawRows
     * @return array{headers: array<int, string>, rows: array<int, array<string, string|int|float|null>>}
     */
    private function mapRowsToHeaders(array $rawRows): array
    {
        $rawRows = array_values(array_filter($rawRows, fn (array $row): bool => collect($row)->contains(fn ($value): bool => trim((string) $value) !== '')));

        if ($rawRows === []) {
            throw new RuntimeException('فایل خالی است.');
        }

        $headers = array_map(fn ($value): string => trim(str_replace("\xEF\xBB\xBF", '', (string) $value)), array_shift($rawRows));
        $rows = [];

        foreach ($rawRows as $rawRow) {
            $row = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = $rawRow[$index] ?? '';
                }
            }

            $rows[] = $row;
        }

        return compact('headers', 'rows');
    }

    /** @param iterable<int, array<int, string|int|float|null>> $rows */
    private function writeXlsx(string $path, array $headers, iterable $rows): void
    {
        $sheetPath = $path.'.sheet.xml';
        $sheet = fopen($sheetPath, 'wb');

        if ($sheet === false) {
            throw new RuntimeException('ساخت برگه خروجی ممکن نیست.');
        }

        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews><sheetData>');
        $rowNumber = 1;
        $this->writeRow($sheet, $rowNumber++, $headers);

        foreach ($rows as $row) {
            $this->writeRow($sheet, $rowNumber++, $row);
        }

        fwrite($sheet, '</sheetData></worksheet>');
        fclose($sheet);

        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sheetPath);
            throw new RuntimeException('ساخت فایل اکسل ممکن نیست.');
        }

        $archive->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $archive->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $archive->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="اطلاعات" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $archive->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $archive->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
        $archive->close();
        @unlink($sheetPath);
    }

    /** @param resource $sheet */
    private function writeRow($sheet, int $rowNumber, iterable $values): void
    {
        fwrite($sheet, '<row r="'.$rowNumber.'">');

        foreach (array_values(is_array($values) ? $values : iterator_to_array($values)) as $index => $value) {
            $cellReference = $this->columnName($index + 1).$rowNumber;

            if (is_int($value) || is_float($value)) {
                fwrite($sheet, '<c r="'.$cellReference.'"><v>'.$value.'</v></c>');
            } else {
                $safeValue = preg_replace('/[^\P{C}\t\n\r]/u', '', (string) ($value ?? '')) ?? '';
                fwrite($sheet, '<c r="'.$cellReference.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($safeValue, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>');
            }
        }

        fwrite($sheet, '</row>');
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = mb_strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return max($index - 1, 0);
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
