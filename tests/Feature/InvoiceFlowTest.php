<?php

use App\Contracts\TaxPlatformGateway;
use App\Enums\BuyerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\SettlementMethod;
use App\Exceptions\MoadianApiException;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\SubmissionResult;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

it('creates an invoice and calculates its totals on the server', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => 'INV-TEST-0001',
        'invoice_date' => today()->format('Y-m-d'),
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 2,
            'unit_price' => 1000000,
            'tax_rate' => 10,
            'discount' => 200000,
        ]],
    ]);

    $invoice = Invoice::whereBelongsTo($user)->firstOrFail();
    $response->assertRedirect(route('invoices.show', $invoice));
    expect((float) $invoice->subtotal)->toBe(2000000.0)
        ->and((float) $invoice->discount_total)->toBe(200000.0)
        ->and((float) $invoice->tax_total)->toBe(180000.0)
        ->and((float) $invoice->total)->toBe(1980000.0)
        ->and($invoice->items)->toHaveCount(1);
});

it('accepts a jalali invoice date and stores its gregorian equivalent', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => 'INV-JALALI-0001',
        'invoice_date_jalali' => '۱۴۰۵/۰۵/۲۴',
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'tax_rate' => 10,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    expect(Invoice::whereBelongsTo($user)->firstOrFail()->invoice_date->format('Y-m-d'))->toBe('2026-08-15');
});

it('stores mixed settlement and validates its cash portion', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => 'INV-MIXED-0001',
        'invoice_date' => today()->format('Y-m-d'),
        'settlement_method' => SettlementMethod::Mixed->value,
        'cash_amount' => 900_000,
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 2,
            'unit_price' => 1_000_000,
            'tax_rate' => 10,
            'discount' => 200_000,
        ]],
    ])->assertRedirect();

    $invoice = Invoice::query()->whereBelongsTo($user)->firstOrFail();
    expect($invoice->settlement_method)->toBe(SettlementMethod::Mixed)
        ->and((float) $invoice->cash_amount)->toBe(900_000.0);

    $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => 'INV-MIXED-INVALID',
        'invoice_date' => today()->format('Y-m-d'),
        'settlement_method' => SettlementMethod::Mixed->value,
        'cash_amount' => 3_000_000,
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 1,
            'unit_price' => 1_000_000,
            'tax_rate' => 10,
            'discount' => 0,
        ]],
    ])->assertSessionHasErrors('cash_amount');
});

it('searches invoices by their unique tax number', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    Invoice::factory()->for($user)->for($customer)->create([
        'number' => 'VISIBLE-INVOICE',
        'tax_id' => 'ABC1230000000000000001',
    ]);
    Invoice::factory()->for($user)->for($customer)->create([
        'number' => 'HIDDEN-INVOICE',
        'tax_id' => 'ABC1230000000000000002',
    ]);

    $this->actingAs($user)
        ->get(route('invoices.index', ['q' => 'ABC1230000000000000001']))
        ->assertSuccessful()
        ->assertSee('VISIBLE-INVOICE')
        ->assertDontSee('HIDDEN-INVOICE');
});

it('moves selected invoices through send confirmation and buyer status', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($user)->for($customer)->create(['status' => InvoiceStatus::Draft]);

    $this->actingAs($user)->post(route('invoices.send'), [
        'invoice_ids' => [$invoice->id],
    ])->assertSessionHas('success');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::AwaitingConfirmation)
        ->and($invoice->submission_uid)->not->toBeNull();

    $this->actingAs($user)->post(route('invoices.confirm_demo', $invoice))->assertSessionHas('success');
    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Confirmed);

    $this->actingAs($user)->patch(route('invoices.buyer_status', $invoice), [
        'buyer_status' => BuyerStatus::Accepted->value,
    ])->assertSessionHas('success');

    expect($invoice->refresh()->buyer_status)->toBe(BuyerStatus::Accepted);
});

it('stores reference numbers returned by moadian that are longer than a uuid', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($user)->for($customer)->create(['status' => InvoiceStatus::Draft]);
    $referenceNumber = 'cSLFe66gE0TjE7SPowPt3z_7YUK_PEcTCEQvHw';

    mock(TaxPlatformGateway::class, function (MockInterface $mock) use ($referenceNumber): void {
        $mock->shouldReceive('submit')->once()->andReturn(new SubmissionResult(
            'f4431dd9-fbaa-46a3-a0ca-171101ec4fbd',
            $referenceNumber,
            'A2W5X6050D100000000065',
        ));
        $mock->shouldReceive('isDemo')->once()->andReturnFalse();
    });

    $this->actingAs($user)
        ->post(route('invoices.send'), ['invoice_ids' => [$invoice->id]])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($invoice->refresh()->reference_number)->toBe($referenceNumber)
        ->and($invoice->status)->toBe(InvoiceStatus::AwaitingConfirmation);
});

it('shows an integration error instead of returning a server error during inquiry', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::AwaitingConfirmation,
        'reference_number' => '967072eb-203e-428e-b9bb-6d2efdb9d356',
    ]);

    mock(TaxPlatformGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isDemo')->once()->andReturnFalse();
        $mock->shouldReceive('inquire')->once()->andThrow(new MoadianApiException('خطای موقت سامانه مودیان'));
    });

    $this->actingAs($user)
        ->post(route('invoices.inquire', $invoice))
        ->assertRedirect()
        ->assertSessionHas('error', 'خطای موقت سامانه مودیان');

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::MoadianError)
        ->and($invoice->last_inquired_at)->not->toBeNull()
        ->and($invoice->error_message)->toBe('خطای موقت سامانه مودیان');
});
