<?php

use App\Enums\InvoiceStatus;
use App\Exceptions\MoadianApiException;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\TaxpayerProfile;
use App\Models\User;
use App\Services\Moadian\InvoiceSerialAllocator;
use App\Services\Moadian\MoadianClientFactory;
use App\Services\MoadianTaxPlatformGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;

beforeEach(function () {
    Cache::flush();
    $key = RSA::createKey(2048);
    $this->privateKey = $key->toString('PKCS8');
    $publicKey = $key->getPublicKey()->toString('PKCS8');
    $this->organizationPublicKey = preg_replace(
        '/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s/',
        '',
        $publicKey,
    );

    config()->set('services.moadian', [
        'driver' => 'real',
        'base_url' => 'https://moadian.test/api/self-tsp',
        'ca_bundle_path' => null,
        'default_measurement_unit_code' => '1627',
        'connect_timeout' => 1,
        'timeout' => 2,
    ]);

    $this->user = User::factory()->create();
    $this->taxpayerProfile = TaxpayerProfile::factory()->for($this->user)->create([
        'fiscal_id' => 'ABC123',
        'economic_code' => '12345678901',
        'private_key' => $this->privateKey,
    ]);
    $this->client = app(MoadianClientFactory::class)->forUser($this->user);
});

it('allocates high monotonically increasing serials for a fiscal memory', function () {
    $customer = Customer::factory()->for($this->user)->create();
    $firstInvoice = Invoice::factory()->for($this->user)->for($customer)->create();
    $secondInvoice = Invoice::factory()->for($this->user)->for($customer)->create();
    $allocator = app(InvoiceSerialAllocator::class);

    $firstSerial = $allocator->allocate($firstInvoice);
    $firstInvoice->update(['moadian_serial' => $firstSerial]);
    $secondSerial = $allocator->allocate($secondInvoice);

    expect($firstSerial)->toBeGreaterThan(1_000_000_000)
        ->and($secondSerial)->toBeGreaterThan($firstSerial);
});

it('looks up a customer from the official economic code endpoint', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'customer-lookup-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [
                'data' => [
                    'economicCode' => '41111111111',
                    'nameTrade' => 'شرکت استعلام‌شده',
                    'nationalId' => '14001234567',
                    'taxpayerType' => 'LEGAL',
                    'addressTaxpayer' => 'تهران',
                    'postalcodeTaxpayer' => '1991912345',
                ],
            ],
        ]);
    });

    $customer = app(MoadianTaxPlatformGateway::class)
        ->lookupCustomer($this->user, '41111111111');

    expect($customer)->toMatchArray([
        'name' => 'شرکت استعلام‌شده',
        'national_id' => '14001234567',
        'type' => 'legal',
        'address' => 'تهران',
        'postal_code' => '1991912345',
    ]);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync/GET_ECONOMIC_CODE_INFORMATION')
        && $request->hasHeader('Authorization', 'Bearer customer-lookup-token')
        && ($request->data()['packet']['fiscalId'] ?? null) === 'ABC123'
        && ($request->data()['packet']['data']['economicCode'] ?? null) === '41111111111');
});

it('looks up a good from the official service and stuff endpoint', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'good-lookup-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [
                'data' => [
                    'result' => [[
                        'itemId' => '10000001',
                        'title' => 'خدمات مشاوره مالیاتی',
                        'unitTitle' => 'ساعت',
                        'unitCode' => '1627',
                        'tax' => 10,
                    ]],
                ],
            ],
        ]);
    });

    $good = app(MoadianTaxPlatformGateway::class)
        ->lookupGood($this->user, '10000001');

    expect($good)->toMatchArray([
        'name' => 'خدمات مشاوره مالیاتی',
        'unit' => 'ساعت',
        'tax_rate' => 10,
        'measurement_unit_code' => '1627',
    ]);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync/GET_SERVICE_STUFF_LIST')
        && $request->hasHeader('Authorization', 'Bearer good-lookup-token')
        && ($request->data()['packet']['fiscalId'] ?? null) === 'ABC123'
        && ($request->data()['packet']['data']['filters'][0] ?? null) === [
            'field' => 'itemId',
            'value' => '10000001',
        ]);
});

it('does not accept a different item returned by the official stuff endpoint', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'good-lookup-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [
                'data' => [
                    'result' => [[
                        'itemId' => '2330000000000',
                        'tax' => 10,
                    ]],
                ],
            ],
        ]);
    });

    expect(app(MoadianTaxPlatformGateway::class)
        ->lookupGood($this->user, '2330002582524'))->toBeNull();
});

it('authenticates, encrypts and submits an invoice packet', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'packetType' => 'TOKEN_RESULT',
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        if (str_ends_with($request->url(), '/sync/GET_SERVER_INFORMATION')) {
            return Http::response([
                'result' => [
                    'packetType' => 'SERVER_INFORMATION',
                    'data' => [
                        'publicKeys' => [[
                            'id' => 'organization-key-id',
                            'key' => $this->organizationPublicKey,
                            'purpose' => 1,
                        ]],
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [[
                'uid' => 'server-uid',
                'referenceNumber' => '967072eb-203e-428e-b9bb-6d2efdb9d356',
                'errorCode' => null,
                'errorDetail' => null,
            ]],
        ]);
    });

    $result = $this->client->submitInvoice([
        'header' => ['taxid' => 'ABC1230481F000000000C2'],
        'body' => [['sstid' => '10000001', 'fee' => 1000]],
        'payments' => [],
        'extension' => null,
    ]);

    expect($result->referenceNumber)->toBe('967072eb-203e-428e-b9bb-6d2efdb9d356')
        ->and($result->taxId)->toBe('ABC1230481F000000000C2');

    Http::assertSentCount(3);
    Http::assertSent(function (Request $request): bool {
        if (! str_ends_with($request->url(), '/async/normal-enqueue')) {
            return false;
        }

        $packet = $request->data()['packets'][0] ?? [];

        return $request->hasHeader('Authorization', 'Bearer test-token')
            && ($packet['packetType'] ?? null) === 'INVOICE.V01'
            && filled($packet['data'] ?? null)
            && filled($packet['dataSignature'] ?? null)
            && ($packet['encryptionKeyId'] ?? null) === 'organization-key-id'
            && filled($request->data()['signature'] ?? null);
    });
});

it('shows the error code and detail returned with an unsuccessful HTTP response', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'errors' => [[
                'errorCode' => '5004',
                'errorDetail' => 'invalid.json.structure',
            ]],
        ], 400);
    });

    expect(fn () => $this->client->economicCodeInformation('41111111111'))
        ->toThrow(
            MoadianApiException::class,
            'سامانه مودیان درخواست را نپذیرفت (HTTP 400): کد 5004: invalid.json.structure',
        );
});

it('inquires the official status by reference number', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [
                'packetType' => 'INQUIRY_RESULT',
                'data' => [[
                    'referenceNumber' => '967072eb-203e-428e-b9bb-6d2efdb9d356',
                    'status' => 'SUCCESS',
                    'data' => ['taxResult' => 'SUCCESS'],
                    'packetType' => 'RECEIVE_INVOICE_CONFIRM',
                    'fiscalId' => 'ABC123',
                ]],
            ],
        ]);
    });

    $result = $this->client->inquiryByReferenceNumber('967072eb-203e-428e-b9bb-6d2efdb9d356');

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->isFailed())->toBeFalse();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_ends_with(
        $request->url(),
        '/sync/INQUIRY_BY_REFERENCE_NUMBER',
    ) && $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('inquires the official status by submission uid and fiscal id', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [
                'data' => [[
                    'uid' => '8a00f17a-bd35-46bc-ae52-3f61fab868c2',
                    'status' => 'SUCCESS',
                    'data' => [
                        'taxResult' => 'SUCCESS',
                        'confirmationReferenceId' => 'official-confirmation-id',
                    ],
                    'packetType' => 'RECEIVE_INVOICE_CONFIRM',
                    'fiscalId' => 'ABC123',
                ]],
            ],
        ]);
    });

    $result = $this->client->inquiryByUid('8a00f17a-bd35-46bc-ae52-3f61fab868c2');

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->confirmationReferenceId)->toBe('official-confirmation-id')
        ->and($result->packetType)->toBe('RECEIVE_INVOICE_CONFIRM');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sync/INQUIRY_BY_UID')
        && ($request->data()['packet']['data'][0] ?? null) === [
            'uid' => '8a00f17a-bd35-46bc-ae52-3f61fab868c2',
            'fiscalId' => 'ABC123',
        ]);
});

it('reads a failed inquiry detail from stringified response data', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [
                'data' => [[
                    'uid' => '8a00f17a-bd35-46bc-ae52-3f61fab868c2',
                    'status' => 'FAILED',
                    'data' => json_encode([
                        'confirmationReferenceId' => null,
                        'taxResult' => 'invalid.service-stuff-id',
                    ]),
                    'packetType' => 'ERROR',
                ]],
            ],
        ]);
    });

    $result = $this->client->inquiryByUid('8a00f17a-bd35-46bc-ae52-3f61fab868c2');

    expect($result->isFailed())->toBeTrue()
        ->and($result->taxResult)->toBe('invalid.service-stuff-id')
        ->and($result->packetType)->toBe('ERROR');
});

it('preserves available failed inquiry data when tax result is absent', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [
                'data' => [[
                    'uid' => '8a00f17a-bd35-46bc-ae52-3f61fab868c2',
                    'status' => 'FAILED',
                    'data' => ['errors' => [['code' => '011049', 'message' => 'invalid service stuff id']]],
                    'packetType' => 'ERROR',
                ]],
            ],
        ]);
    });

    $result = $this->client->inquiryByUid('8a00f17a-bd35-46bc-ae52-3f61fab868c2');

    expect($result->isFailed())->toBeTrue()
        ->and($result->taxResult)->toContain('011049')
        ->and($result->taxResult)->toContain('invalid service stuff id');
});

it('formats the singular error list returned by a failed invoice inquiry', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [
                'data' => [[
                    'uid' => '8a00f17a-bd35-46bc-ae52-3f61fab868c2',
                    'status' => 'FAILED',
                    'data' => [
                        'error' => [[
                            'code' => '4212',
                            'message' => 'امضا بسته صحیح نمی باشد.',
                        ]],
                        'warning' => [],
                        'success' => false,
                    ],
                    'packetType' => 'RECEIVE_INVOICE_CONFIRM',
                ]],
            ],
        ]);
    });

    $result = $this->client->inquiryByUid('8a00f17a-bd35-46bc-ae52-3f61fab868c2');

    expect($result->isFailed())->toBeTrue()
        ->and($result->taxResult)->toBe('کد 4212: امضا بسته صحیح نمی باشد.')
        ->and($result->packetType)->toBe('RECEIVE_INVOICE_CONFIRM');
});

it('preserves the uid and marks a repeated submission as retry', function () {
    $asyncAttempts = 0;
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use (&$asyncAttempts) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        if (str_ends_with($request->url(), '/sync/GET_SERVER_INFORMATION')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'publicKeys' => [[
                            'id' => 'organization-key-id',
                            'key' => $this->organizationPublicKey,
                            'purpose' => 1,
                        ]],
                    ],
                ],
            ]);
        }

        $asyncAttempts++;

        if ($asyncAttempts === 1) {
            return Http::response([
                'result' => [[
                    'uid' => null,
                    'referenceNumber' => null,
                    'errorCode' => '5008',
                    'errorDetail' => 'temporary-rejection',
                ]],
            ]);
        }

        return Http::response([
            'result' => [[
                'uid' => 'server-uid',
                'referenceNumber' => '967072eb-203e-428e-b9bb-6d2efdb9d356',
                'errorCode' => null,
                'errorDetail' => null,
            ]],
        ]);
    });

    $user = $this->user;
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create(['measurement_unit_code' => '1627']);
    $invoice = Invoice::factory()->for($user)->for($customer)->create();
    $invoice->items()->create([
        'good_id' => $good->id,
        'description' => $good->name,
        'commodity_code' => $good->commodity_code,
        'quantity' => 1,
        'unit_price' => 10_000_000,
        'tax_rate' => 10,
        'discount' => 0,
        'subtotal' => 10_000_000,
        'tax_amount' => 1_000_000,
        'total' => 11_000_000,
    ]);

    $gateway = app(MoadianTaxPlatformGateway::class);

    expect(fn () => $gateway->submit($invoice))->toThrow(MoadianApiException::class);

    $firstUid = $invoice->refresh()->submission_uid;
    $firstTaxId = $invoice->tax_id;
    $firstSerial = $invoice->moadian_serial;
    $result = $gateway->submit($invoice);
    $asyncRequests = Http::recorded(
        fn (Request $request) => str_ends_with($request->url(), '/async/normal-enqueue'),
    )->values();
    $firstPacket = $asyncRequests[0][0]->data()['packets'][0];
    $secondPacket = $asyncRequests[1][0]->data()['packets'][0];

    expect($firstUid)->not->toBeNull()
        ->and($firstTaxId)->not->toBeNull()
        ->and($firstSerial)->not->toBeNull()
        ->and($result->uid)->toBe($firstUid)
        ->and($result->taxId)->toBe($firstTaxId)
        ->and($firstPacket['uid'])->toBe($firstUid)
        ->and($firstPacket['retry'])->toBeFalse()
        ->and($secondPacket['uid'])->toBe($firstUid)
        ->and($secondPacket['retry'])->toBeTrue();
});

it('uses a new uid after a previously accepted packet receives a definitive failure', function () {
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/sync/GET_TOKEN')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'token' => 'test-token',
                        'expiresIn' => (int) floor(microtime(true) * 1000) + 600_000,
                    ],
                ],
            ]);
        }

        if (str_ends_with($request->url(), '/sync/GET_SERVER_INFORMATION')) {
            return Http::response([
                'result' => [
                    'data' => [
                        'publicKeys' => [[
                            'id' => 'organization-key-id',
                            'key' => $this->organizationPublicKey,
                            'purpose' => 1,
                        ]],
                    ],
                ],
            ]);
        }

        return Http::response([
            'result' => [[
                'uid' => 'server-uid',
                'referenceNumber' => 'new-reference-number',
                'errorCode' => null,
                'errorDetail' => null,
            ]],
        ]);
    });

    $customer = Customer::factory()->for($this->user)->create();
    $good = Good::factory()->for($this->user)->create(['measurement_unit_code' => '1627']);
    $invoice = Invoice::factory()->for($this->user)->for($customer)->create([
        'status' => InvoiceStatus::MoadianError,
        'submission_uid' => '8a00f17a-bd35-46bc-ae52-3f61fab868c2',
        'moadian_serial' => 7,
        'tax_id' => 'ABC1230481F00000000072',
        'reference_number' => 'failed-reference-number',
    ]);
    $invoice->items()->create([
        'good_id' => $good->id,
        'description' => $good->name,
        'commodity_code' => $good->commodity_code,
        'quantity' => 1,
        'unit_price' => 10_000_000,
        'tax_rate' => 10,
        'discount' => 0,
        'subtotal' => 10_000_000,
        'tax_amount' => 1_000_000,
        'total' => 11_000_000,
    ]);

    $result = app(MoadianTaxPlatformGateway::class)->submit($invoice);
    $invoice->refresh();
    $packet = Http::recorded(
        fn (Request $request) => str_ends_with($request->url(), '/async/normal-enqueue'),
    )->first()[0]->data()['packets'][0];

    expect($result->uid)->not->toBe('8a00f17a-bd35-46bc-ae52-3f61fab868c2')
        ->and($packet['uid'])->toBe($result->uid)
        ->and($packet['retry'])->toBeFalse()
        ->and($invoice->reference_number)->toBeNull()
        ->and($invoice->moadian_serial)->toBeGreaterThan(1_000_000_000)
        ->and($result->taxId)->not->toBe('ABC1230481F00000000072')
        ->and(substr($result->taxId, 11, 10))->toBe(strtoupper(str_pad(dechex($invoice->moadian_serial), 10, '0', STR_PAD_LEFT)));
});
