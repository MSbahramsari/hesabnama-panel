<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ZipArchive;

class OfficialStuffCatalogClient
{
    private const CREATE_SESSION_QUERY = <<<'GRAPHQL'
        query {
          identity_create_session {
            data
            statusCode
            message
          }
        }
        GRAPHQL;

    private const LOOKUP_QUERY = <<<'GRAPHQL'
        query getInfoByGuids($input: GetInformationWithIDFilterServiceInputDto32973QueryInput) {
          GetInformationWithIDFilter(input: $input) {
            data {
              fileName
            }
            message
            statusCode
          }
        }
        GRAPHQL;

    /**
     * @return array{item_id: string, description: string, type: ?string, vat: float, taxable: ?string, source_created_date: ?string, effective_date: ?string, expiration_date: ?string, source_updated_date: ?string}|null
     */
    public function lookup(string $commodityCode): ?array
    {
        if (preg_match('/^\d{13}$/', $commodityCode) !== 1) {
            return null;
        }

        try {
            $cached = Cache::remember(
                "stuff-catalog:official-lookup:{$commodityCode}",
                now()->addDay(),
                fn (): array => $this->request($commodityCode) ?? ['missing' => true],
            );

            return isset($cached['missing']) ? null : $cached;
        } catch (Throwable $exception) {
            Log::warning('Official stuff catalog lookup failed.', [
                'commodity_code' => $commodityCode,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{item_id: string, description: string, type: ?string, vat: float, taxable: ?string, source_created_date: ?string, effective_date: ?string, expiration_date: ?string, source_updated_date: ?string}|null
     */
    private function request(string $commodityCode): ?array
    {
        $baseUrl = rtrim((string) config('services.stuff_catalog.portal_url'), '/');
        $sessionResponse = $this->http()->post("{$baseUrl}/auth/gs/graphql", [
            'query' => self::CREATE_SESSION_QUERY,
            'variables' => (object) [],
        ]);

        if (! $sessionResponse->successful()) {
            throw new RuntimeException("Official stuff catalog session failed with HTTP {$sessionResponse->status()}.");
        }

        $sessionId = (string) $sessionResponse->json('data.identity_create_session.data', '');

        if ($sessionId === '') {
            throw new RuntimeException('Official stuff catalog did not return a guest session.');
        }

        $lookupResponse = $this->http()
            ->withHeader('SessionID', $sessionId)
            ->post("{$baseUrl}/StuffRate/gs/graphql", [
                'query' => self::LOOKUP_QUERY,
                'variables' => [
                    'input' => [
                        'isService' => null,
                        'exportType' => 0,
                        'startDate' => null,
                        'endDate' => null,
                        'code' => $commodityCode,
                        'title' => '',
                    ],
                ],
            ]);

        if (! $lookupResponse->successful()) {
            throw new RuntimeException("Official stuff catalog lookup failed with HTTP {$lookupResponse->status()}.");
        }

        $fileName = (string) $lookupResponse->json('data.GetInformationWithIDFilter.data.fileName', '');

        if (preg_match('/^[A-Za-z0-9_.-]+\.zip$/', $fileName) !== 1) {
            return null;
        }

        $downloadResponse = $this->http()
            ->withHeader('SessionID', $sessionId)
            ->get("{$baseUrl}/upload/gs/api/v1/exportguest/download/{$fileName}");

        if (! $downloadResponse->successful()) {
            throw new RuntimeException("Official stuff catalog download failed with HTTP {$downloadResponse->status()}.");
        }

        $archive = $downloadResponse->body();

        if ($archive === '' || strlen($archive) > 5 * 1024 * 1024) {
            throw new RuntimeException('Official stuff catalog returned an invalid lookup archive.');
        }

        return $this->parseArchive($archive, $commodityCode);
    }

    /**
     * @return array{item_id: string, description: string, type: ?string, vat: float, taxable: ?string, source_created_date: ?string, effective_date: ?string, expiration_date: ?string, source_updated_date: ?string}|null
     */
    private function parseArchive(string $archive, string $commodityCode): ?array
    {
        $path = tempnam(sys_get_temp_dir(), 'stuffid-');

        if ($path === false || file_put_contents($path, $archive) === false) {
            throw new RuntimeException('Could not create the official catalog temporary file.');
        }

        $zip = new ZipArchive;
        $isOpen = false;

        try {
            if ($zip->open($path) !== true || $zip->numFiles < 1) {
                throw new RuntimeException('Official stuff catalog archive could not be opened.');
            }

            $isOpen = true;

            $csv = $zip->getFromIndex(0);

            if (! is_string($csv)) {
                return null;
            }

            return $this->parseCsv($csv, $commodityCode);
        } finally {
            if ($isOpen) {
                $zip->close();
            }

            @unlink($path);
        }
    }

    /**
     * @return array{item_id: string, description: string, type: ?string, vat: float, taxable: ?string, source_created_date: ?string, effective_date: ?string, expiration_date: ?string, source_updated_date: ?string}|null
     */
    private function parseCsv(string $csv, string $commodityCode): ?array
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            throw new RuntimeException('Could not parse the official stuff catalog response.');
        }

        try {
            fwrite($stream, $csv);
            rewind($stream);
            $headers = fgetcsv($stream, null, ',', '"', '\\');

            if (! is_array($headers)) {
                return null;
            }

            $headers = array_map(fn (mixed $header): string => mb_strtolower(trim((string) $header, "\xEF\xBB\xBF \t\n\r\0\x0B")), $headers);

            while (($row = fgetcsv($stream, null, ',', '"', '\\')) !== false) {
                $values = array_combine($headers, array_slice(array_pad($row, count($headers), null), 0, count($headers)));

                if (! is_array($values) || (string) Arr::get($values, 'id') !== $commodityCode) {
                    continue;
                }

                $description = trim((string) Arr::get($values, 'descriptionofid'));

                if ($description === '') {
                    return null;
                }

                return [
                    'item_id' => $commodityCode,
                    'description' => $description,
                    'type' => $this->nullable($values['type'] ?? null),
                    'vat' => max(0, min(100, (float) ($values['vat'] ?? 0))),
                    'taxable' => $this->nullable($values['taxable'] ?? null),
                    'source_created_date' => $this->nullable($values['createdate'] ?? null),
                    'effective_date' => $this->nullable($values['rundate'] ?? null),
                    'expiration_date' => $this->nullable($values['expirationdate'] ?? null),
                    'source_updated_date' => $this->nullable($values['lasteditdate'] ?? null),
                ];
            }

            return null;
        } finally {
            fclose($stream);
        }
    }

    private function http(): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->retry([100, 300], throw: false);

        $caBundlePath = config('services.stuff_catalog.ca_bundle_path');

        if (is_string($caBundlePath) && $caBundlePath !== '') {
            $request->withOptions(['verify' => $caBundlePath]);
        }

        return $request;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
