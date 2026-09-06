<?php

use App\Enums\InvoiceStatus;
use App\Exceptions\MoadianConfigurationException;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\InvoiceSubmissionValidator;

it('rejects future and expired normal invoice dates in the form', function (string $date) {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => fake()->unique()->numerify('INV-#####'),
        'invoice_date' => $date,
        'settlement_method' => 'cash',
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 1,
            'unit_price' => 1_000_000,
            'tax_rate' => 10,
            'discount' => 0,
        ]],
    ])->assertSessionHasErrors('invoice_date');
})->with([
    'future date' => fn (): string => today('Asia/Tehran')->addDay()->format('Y-m-d'),
    'older than normal window' => fn (): string => today('Asia/Tehran')->subDays(13)->format('Y-m-d'),
]);

it('accepts the last day of the twelve day normal submission window', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => 'INV-LAST-DAY',
        'invoice_date' => today('Asia/Tehran')->subDays(12)->format('Y-m-d'),
        'settlement_method' => 'cash',
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 1,
            'unit_price' => 1_000_000,
            'tax_rate' => 10,
            'discount' => 0,
        ]],
    ])->assertSessionDoesntHaveErrors();

    expect(Invoice::query()->where('number', 'INV-LAST-DAY')->exists())->toBeTrue();
});

it('blocks legacy invalid commodity identifiers before submission', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create(['commodity_code' => '12345678']);
    $invoice = Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::Draft,
        'subtotal' => 1_000_000,
        'discount_total' => 0,
        'tax_total' => 100_000,
        'total' => 1_100_000,
        'cash_amount' => 1_000_000,
    ]);
    $invoice->items()->create([
        'good_id' => $good->id,
        'description' => $good->name,
        'commodity_code' => $good->commodity_code,
        'quantity' => 1,
        'unit_price' => 1_000_000,
        'tax_rate' => 10,
        'discount' => 0,
        'subtotal' => 1_000_000,
        'tax_amount' => 100_000,
        'total' => 1_100_000,
    ]);

    expect(fn () => app(InvoiceSubmissionValidator::class)->assertValid($invoice))
        ->toThrow(MoadianConfigurationException::class, 'دقیقاً ۱۳ رقم');
});
