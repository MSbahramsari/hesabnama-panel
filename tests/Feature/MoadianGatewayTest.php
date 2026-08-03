<?php

use App\Exceptions\MoadianApiException;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\MoadianClient;
use App\Services\MoadianTaxPlatformGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use phpseclib3\Crypt\RSA;

beforeEach(function () {
    Cache::flush();
    $key = RSA::createKey(2048);
    $privateKey = $key->toString('PKCS8');
    $this->privateKeyPath = tempnam(sys_get_temp_dir(), 'moadian-test-key-');
    file_put_contents($this->privateKeyPath, $privateKey);
    $publicKey = $key->getPublicKey()->toString('PKCS8');
    $this->organizationPublicKey = preg_replace(
        '/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s/',
        '',
        $publicKey,
    );

    config()->set('services.moadian', [
        'driver' => 'real',
        'base_url' => 'https://moadian.test/api/self-tsp',
        'fiscal_id' => 'ABC123',
        'seller_economic_code' => '12345678901',
        'seller_branch_code' => null,
        'private_key_path' => $this->privateKeyPath,
        'ca_bundle_path' => null,
        'default_measurement_unit_code' => '1627',
        'connect_timeout' => 1,
        'timeout' => 2,
    ]);
});

afterEach(function () {
    if (is_file($this->privateKeyPath)) {
        unlink($this->privateKeyPath);
    }
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

    $result = app(MoadianClient::class)->submitInvoice([
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

    $result = app(MoadianClient::class)
        ->inquiryByReferenceNumber('967072eb-203e-428e-b9bb-6d2efdb9d356');

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->isFailed())->toBeFalse();

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => str_ends_with(
        $request->url(),
        '/sync/INQUIRY_BY_REFERENCE_NUMBER',
    ) && $request->hasHeader('Authorization', 'Bearer test-token'));
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

    $user = User::factory()->create();
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
    $result = $gateway->submit($invoice);
    $asyncRequests = Http::recorded(
        fn (Request $request) => str_ends_with($request->url(), '/async/normal-enqueue'),
    )->values();
    $firstPacket = $asyncRequests[0][0]->data()['packets'][0];
    $secondPacket = $asyncRequests[1][0]->data()['packets'][0];

    expect($firstUid)->not->toBeNull()
        ->and($firstTaxId)->not->toBeNull()
        ->and($result->uid)->toBe($firstUid)
        ->and($firstPacket['uid'])->toBe($firstUid)
        ->and($firstPacket['retry'])->toBeFalse()
        ->and($secondPacket['uid'])->toBe($firstUid)
        ->and($secondPacket['retry'])->toBeTrue();
});
