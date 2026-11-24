<?php

use App\Actions\SaveInvoiceAction;
use App\Enums\SettlementMethod;
use App\Models\Customer;
use App\Models\Good;
use App\Models\TaxpayerProfile;
use App\Models\User;
use App\Services\Moadian\InvoicePayloadFactory;
use App\Services\Moadian\MoadianClientFactory;
use App\Services\Moadian\RialInvoiceCalculator;
use Illuminate\Validation\ValidationException;
use phpseclib3\Crypt\RSA;

it('applies v7.9 field precision using decimal multiplication before truncation', function (string $quantity, string $price, string $rate, string $discount, array $expected) {
    expect(app(RialInvoiceCalculator::class)->line($quantity, $price, $rate, $discount))->toBe($expected);
})->with([
    'reported invoice' => ['1', '1818181818', '10', '0', ['subtotal' => 1818181818, 'discount' => 0, 'tax_amount' => 181818181, 'total' => 1999999999]],
    'eight decimal price and fractional discount' => ['10', '1.99999999', '7.25', '1.99', ['subtotal' => 19, 'discount' => 1, 'tax_amount' => 1, 'total' => 19]],
    'eight decimal quantity' => ['0.00000001', '100000000', '0', '0', ['subtotal' => 1, 'discount' => 0, 'tax_amount' => 0, 'total' => 1]],
    'less than one rial vat' => ['1', '101', '0.99', '0', ['subtotal' => 101, 'discount' => 0, 'tax_amount' => 0, 'total' => 101]],
    'beyond binary float integer precision' => ['1', '9007199254740993', '0', '0', ['subtotal' => 9007199254740993, 'discount' => 0, 'tax_amount' => 0, 'total' => 9007199254740993]],
]);

it('saves and submits the same truncated multi row amounts for each settlement method', function (SettlementMethod $method, int $cash, int $share, int $vatShare) {
    $user = User::factory()->create();
    $profile = TaxpayerProfile::factory()->for($user)->create([
        'private_key' => RSA::createKey(2048)->toString('PKCS8'),
        'economic_code' => '12345678901',
    ]);
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create(['measurement_unit_code' => '1627']);
    $item = ['good_id' => $good->id, 'quantity' => '1', 'unit_price' => '1818181818', 'tax_rate' => '10', 'discount' => '0'];
    $invoice = app(SaveInvoiceAction::class)->handle($user, [
        'number' => 'PRECISION-TEST', 'customer_id' => $customer->id,
        'invoice_date' => today()->format('Y-m-d'), 'settlement_method' => $method->value,
        'cash_amount' => $cash, 'items' => [$item, $item],
    ]);
    $payload = app(InvoicePayloadFactory::class)->make($invoice, app(MoadianClientFactory::class)->configuration($profile));

    expect($invoice->tax_total)->toBe('363636362.00')
        ->and($invoice->total)->toBe('3999999998.00')
        ->and($payload['header']['tbill'])->toBe(3999999998)
        ->and($payload['header']['cap'])->toBe($cash)
        ->and($payload['header']['insp'])->toBe(3636363636 - $cash)
        ->and($payload['header']['tvop'])->toBe(2 * $vatShare)
        ->and($payload['body'][0]['cop'])->toBe($share)
        ->and($payload['body'][0]['vop'])->toBe($vatShare);
})->with([
    'cash' => [SettlementMethod::Cash, 3636363636, 1999999999, 181818181],
    'credit' => [SettlementMethod::Credit, 0, 0, 0],
    'mixed' => [SettlementMethod::Mixed, 909090909, 499999999, 45454545],
]);

it('preserves eight decimals through draft creation and rejects excessive input precision', function () {
    $user = User::factory()->create();
    $good = Good::factory()->for($user)->create();
    $data = [
        'electronic_invoice_type' => 2, 'number' => 'EIGHT-DECIMALS',
        'invoice_date' => today()->format('Y-m-d'),
        'items' => [['good_id' => $good->id, 'quantity' => '1.00000001', 'unit_price' => '100.12345678', 'tax_rate' => '10']],
    ];
    $this->actingAs($user)->post(route('invoices.store'), $data)->assertSessionDoesntHaveErrors();
    $invoice = $user->invoices()->firstOrFail();
    expect($invoice->items->first()->quantity)->toBe('1.00000001')
        ->and($invoice->items->first()->unit_price)->toBe('100.12345678')
        ->and($invoice->subtotal)->toBe('100.00')
        ->and($invoice->tax_total)->toBe('10.00');

    $data['number'] = 'TOO-MANY-DECIMALS';
    $data['items'][0]['unit_price'] = '1.123456789';
    $this->post(route('invoices.store'), $data)->assertSessionHasErrors('items.0.unit_price');
});

it('rejects mixed cash amounts that truncate to zero without saving a draft', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();
    expect(fn () => app(SaveInvoiceAction::class)->handle($user, [
        'number' => 'ZERO-CASH', 'customer_id' => $customer->id,
        'invoice_date' => today()->format('Y-m-d'), 'settlement_method' => 'mixed', 'cash_amount' => '0.99',
        'items' => [['good_id' => $good->id, 'quantity' => '1', 'unit_price' => '100', 'tax_rate' => '10']],
    ]))->toThrow(ValidationException::class);
    expect($user->invoices()->count())->toBe(0);
});

it('multiplies before dividing payment shares without a floating point ratio', function () {
    $calculator = app(RialInvoiceCalculator::class);
    expect($calculator->paymentShare(9999999999999999, 9999999999999998, 9999999999999999))
        ->toBe(9999999999999998)
        ->and($calculator->paymentShare(11, 1, 3))->toBe(3)
        ->and($calculator->paymentShare(1, 1, 3))->toBe(0);
});

it('rejects zero rial rows and excessive products atomically', function (string $quantity, string $price) {
    $user = User::factory()->create();
    $good = Good::factory()->for($user)->create();
    $this->actingAs($user)->post(route('invoices.store'), [
        'electronic_invoice_type' => 2, 'number' => 'INVALID-PRODUCT',
        'invoice_date' => today()->format('Y-m-d'),
        'items' => [['good_id' => $good->id, 'quantity' => $quantity, 'unit_price' => $price, 'tax_rate' => '10']],
    ])->assertSessionHasErrors('items');

    expect($user->invoices()->count())->toBe(0);
})->with([
    'fraction smaller than a rial' => ['0.00000001', '1'],
    'product larger than storage capacity' => ['999999999', '9999999999999999'],
]);
