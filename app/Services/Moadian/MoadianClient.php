<?php

namespace App\Services\Moadian;

use App\Exceptions\MoadianApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MoadianClient
{
    public function __construct(
        private MoadianConfiguration $configuration,
        private MoadianNormalizer $normalizer,
        private MoadianCrypto $crypto,
    ) {}

    /** @return array{id: string, key: string} */
    public function encryptionKey(): array
    {
        return Cache::remember($this->configuration->encryptionKeyCacheKey(), now()->addDay(), function (): array {
            $response = $this->post('/sync/GET_SERVER_INFORMATION', [
                'packet' => $this->packet('GET_SERVER_INFORMATION', null, ''),
                'signature' => null,
                'signatureKeyId' => null,
            ], $this->headers());

            $keys = Arr::get($this->responseData($response), 'publicKeys', []);
            $key = collect($keys)->firstWhere('purpose', 1) ?? collect($keys)->first();

            if (! is_array($key) || blank($key['id'] ?? null) || blank($key['key'] ?? null)) {
                throw new MoadianApiException('کلید عمومی رمزنگاری در پاسخ سامانه مودیان یافت نشد.');
            }

            return ['id' => (string) $key['id'], 'key' => (string) $key['key']];
        });
    }

    /** @return array<string, mixed>|null */
    public function economicCodeInformation(string $economicCode): ?array
    {
        $data = $this->authenticatedSync('GET_ECONOMIC_CODE_INFORMATION', ['economicCode' => $economicCode]);

        if ($data === [] || blank($data['economicCode'] ?? $economicCode)) {
            return null;
        }

        return $data;
    }

    /** @return array<string, mixed>|null */
    public function serviceStuffInformation(string $commodityCode): ?array
    {
        $data = $this->authenticatedSync('GET_SERVICE_STUFF_LIST', [
            'filters' => [['field' => 'itemId', 'value' => $commodityCode]],
            'page' => 1,
            'size' => 1,
        ]);

        $item = Arr::get($data, 'result.0');

        return is_array($item) ? $item : null;
    }

    /** @param array<string, mixed> $invoice */
    public function submitInvoice(array $invoice, ?string $uid = null, bool $retry = false): SubmissionResult
    {
        $this->configuration->assertReadyForSubmission();
        $headers = $this->authenticatedHeaders();
        $uid ??= (string) Str::uuid();
        $dataSignature = $this->crypto->sign(
            $this->normalizer->normalize($invoice),
            $this->configuration->privateKeyPem(),
        );
        $encryptionKey = $this->encryptionKey();
        $encrypted = $this->crypto->encryptPayload($invoice, $encryptionKey['key'], $encryptionKey['id']);
        $packet = array_merge($this->packet('INVOICE.V01', $encrypted['data']), $encrypted, [
            'uid' => $uid,
            'retry' => $retry,
            'fiscalId' => $this->configuration->fiscalId(),
            'dataSignature' => $dataSignature,
        ]);
        $signature = $this->signRequest(['packets' => [$packet]], $headers);
        $response = $this->post('/async/normal-enqueue', [
            'packets' => [$packet],
            'signature' => $signature,
            'signatureKeyId' => null,
        ], $headers);

        $result = Arr::get($this->decode($response), 'result.0');

        if (! is_array($result) || filled($result['errorCode'] ?? null)) {
            $detail = is_array($result) ? ($result['errorDetail'] ?? $result['errorCode'] ?? null) : null;

            throw new MoadianApiException('سامانه مودیان ارسال صورتحساب را نپذیرفت'.($detail ? ": {$detail}" : '.'));
        }

        $referenceNumber = (string) ($result['referenceNumber'] ?? '');

        if ($referenceNumber === '') {
            throw new MoadianApiException('سامانه مودیان کد رهگیری ارسال را برنگرداند.');
        }

        return new SubmissionResult($uid, $referenceNumber, (string) Arr::get($invoice, 'header.taxid'));
    }

    public function inquiryByReferenceNumber(string $referenceNumber): InquiryResult
    {
        $data = $this->authenticatedSync('INQUIRY_BY_REFERENCE_NUMBER', [
            'referenceNumber' => [$referenceNumber],
        ]);
        $result = $data[0] ?? null;

        if (! is_array($result)) {
            throw new MoadianApiException('پاسخ استعلام سامانه مودیان قابل پردازش نیست.');
        }

        return new InquiryResult(
            mb_strtoupper((string) ($result['status'] ?? 'PENDING')),
            filled(Arr::get($result, 'data.taxResult')) ? (string) Arr::get($result, 'data.taxResult') : null,
        );
    }

    public function token(): string
    {
        $this->configuration->assertReadyForAuthenticatedRequests();
        $cacheKey = $this->configuration->tokenCacheKey();
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && (int) ($cached['expires_at'] ?? 0) > $this->timestamp() + 30_000) {
            return (string) $cached['token'];
        }

        $headers = $this->headers();
        $packet = $this->packet('GET_TOKEN', ['username' => $this->configuration->fiscalId()]);
        $packet['fiscalId'] = $this->configuration->fiscalId();
        $response = $this->post('/sync/GET_TOKEN', [
            'packet' => $packet,
            'signature' => $this->signRequest($packet, $headers),
            'signatureKeyId' => null,
        ], $headers);
        $data = $this->responseData($response);
        $token = (string) ($data['token'] ?? '');
        $expiresAt = (int) ($data['expiresIn'] ?? 0);

        if ($token === '' || $expiresAt <= $this->timestamp()) {
            throw new MoadianApiException('توکن معتبر از سامانه مودیان دریافت نشد.');
        }

        Cache::put($cacheKey, [
            'token' => $token,
            'expires_at' => $expiresAt,
        ], now()->addMilliseconds(max(1, $expiresAt - $this->timestamp() - 30_000)));

        return $token;
    }

    /** @param array<string, mixed> $data */
    private function authenticatedSync(string $packetType, array $data): array
    {
        $headers = $this->authenticatedHeaders();
        $packet = $this->packet($packetType, $data);
        $packet['fiscalId'] = $this->configuration->fiscalId();
        $response = $this->post("/sync/{$packetType}", [
            'packet' => $packet,
            'signature' => $this->signRequest($packet, $headers),
            'signatureKeyId' => null,
        ], $headers);

        return $this->responseData($response);
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'requestTraceId' => (string) Str::uuid(),
            'timestamp' => (string) $this->timestamp(),
        ];
    }

    /** @return array<string, string> */
    private function authenticatedHeaders(): array
    {
        return array_merge($this->headers(), ['Authorization' => 'Bearer '.$this->token()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function packet(string $packetType, mixed $data, ?string $fiscalId = null): array
    {
        return [
            'uid' => (string) Str::uuid(),
            'packetType' => $packetType,
            'retry' => false,
            'data' => $data,
            'encryptionKeyId' => '',
            'symmetricKey' => '',
            'iv' => '',
            'fiscalId' => $fiscalId ?? $this->configuration->fiscalId(),
            'dataSignature' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function signRequest(array $payload, array $headers): string
    {
        $signatureHeaders = $headers;

        if (isset($signatureHeaders['Authorization'])) {
            $signatureHeaders['Authorization'] = Str::after($signatureHeaders['Authorization'], 'Bearer ');
        }

        return $this->crypto->sign(
            $this->normalizer->normalize(array_merge($payload, $signatureHeaders)),
            $this->configuration->privateKeyPem(),
        );
    }

    /** @param array<string, mixed> $payload */
    private function post(string $path, array $payload, array $headers): Response
    {
        try {
            $response = $this->http()
                ->withHeaders($headers)
                ->post($path, $payload);
        } catch (Throwable $exception) {
            throw new MoadianApiException('ارتباط شبکه با سامانه مودیان برقرار نشد.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new MoadianApiException("سامانه مودیان با کد HTTP {$response->status()} پاسخ ناموفق داد.");
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl($this->configuration->baseUrl())
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->configuration->connectTimeout())
            ->timeout($this->configuration->timeout());

        if ($this->configuration->caBundlePath() !== null) {
            $request->withOptions(['verify' => $this->configuration->caBundlePath()]);
        }

        return $request;
    }

    /** @return array<string, mixed> */
    private function responseData(Response $response): array
    {
        $result = Arr::get($this->decode($response), 'result');

        if (! is_array($result)) {
            throw new MoadianApiException('ساختار پاسخ سامانه مودیان معتبر نیست.');
        }

        if (filled($result['errorCode'] ?? null)) {
            $detail = $result['errorDetail'] ?? $result['errorCode'];
            throw new MoadianApiException("سامانه مودیان خطا برگرداند: {$detail}");
        }

        $data = $result['data'] ?? [];

        if (! is_array($data)) {
            throw new MoadianApiException('داده پاسخ سامانه مودیان قابل پردازش نیست.');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            throw new MoadianApiException('پاسخ سامانه مودیان JSON معتبر نیست.');
        }

        return $json;
    }

    private function timestamp(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
