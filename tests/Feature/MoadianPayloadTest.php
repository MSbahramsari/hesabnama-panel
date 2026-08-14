<?php

use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\TaxpayerProfile;
use App\Models\User;
use App\Services\Moadian\InvoicePayloadFactory;
use App\Services\Moadian\MoadianClientFactory;
use phpseclib3\Crypt\RSA;

beforeEach(function () {
    $this->privateKey = RSA::createKey(2048)->toString('PKCS8');

    config()->set('services.moadian', [
        'driver' => 'real',
        'base_url' => 'https://moadian.test/api/self-tsp',
        'ca_bundle_path' => null,
        'default_measurement_unit_code' => null,
        'connect_timeout' => 1,
        'timeout' => 2,
    ]);
});

it('builds a version one invoice payload from persisted invoice data', function () {
    $user = User::factory()->create();
    $taxpayerProfile = TaxpayerProfile::factory()->for($user)->create([
        'fiscal_id' => 'ABC123',
        'economic_code' => '12345678901',
        'private_key' => $this->privateKey,
    ]);
    $customer = Customer::factory()->for($user)->create(['type' => 'legal']);
    $good = Good::factory()->for($user)->create(['measurement_unit_code' => '1627']);
    $invoice = Invoice::factory()->for($user)->for($customer)->create([
        'subtotal' => 2_000_000,
        'discount_total' => 200_000,
        'tax_total' => 180_000,
        'total' => 1_980_000,
    ]);
    $invoice->items()->create([
        'good_id' => $good->id,
        'description' => $good->name,
        'commodity_code' => $good->commodity_code,
        'quantity' => 2,
        'unit_price' => 1_000_000,
        'tax_rate' => 10,
        'discount' => 200_000,
        'subtotal' => 2_000_000,
        'tax_amount' => 180_000,
        'total' => 1_980_000,
    ]);

    $configuration = app(MoadianClientFactory::class)->configuration($taxpayerProfile);
    $payload = app(InvoicePayloadFactory::class)->make($invoice, $configuration);

    expect($payload['header'])
        ->toMatchArray([
            'inty' => 1,
            'inp' => 1,
            'ins' => 1,
            'tins' => '12345678901',
            'tinb' => $customer->economic_code,
            'tob' => 2,
            'tprdis' => 2_000_000,
            'tdis' => 200_000,
            'tvam' => 180_000,
            'tbill' => 1_980_000,
        ])
        ->and($payload['header']['taxid'])->toStartWith('ABC123')->toHaveLength(22)
        ->and($payload['body'][0])
        ->toMatchArray([
            'sstid' => $good->commodity_code,
            'mu' => '1627',
            'am' => 2.0,
            'fee' => 1_000_000,
            'dis' => 200_000,
            'vam' => 180_000,
            'tsstam' => 1_980_000,
        ]);
});
