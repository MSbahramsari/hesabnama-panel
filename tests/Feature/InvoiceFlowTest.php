<?php

use App\Contracts\TaxPlatformGateway;
use App\Enums\BuyerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MoadianStatus;
use App\Enums\SettlementMethod;
use App\Exceptions\MoadianApiException;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\SubmissionResult;
use App\Support\JalaliDate;
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

it('accepts visually grouped invoice amounts', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => 'INV-GROUPED-AMOUNTS',
        'invoice_date' => today()->format('Y-m-d'),
        'settlement_method' => SettlementMethod::Mixed->value,
        'cash_amount' => '۹۰۰٬۰۰۰',
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 2,
            'unit_price' => '1,000,000',
            'tax_rate' => 10,
            'discount' => '۲۰۰٬۰۰۰',
        ]],
    ])->assertRedirect();

    $invoice = Invoice::query()->whereBelongsTo($user)->firstOrFail();

    expect((float) $invoice->cash_amount)->toBe(900_000.0)
        ->and((float) $invoice->subtotal)->toBe(2_000_000.0)
        ->and((float) $invoice->discount_total)->toBe(200_000.0);
});

it('ignores a zero cash amount unless the settlement method is mixed', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $response = $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => 'INV-CASH-WITH-HIDDEN-ZERO',
        'invoice_date' => today()->format('Y-m-d'),
        'settlement_method' => SettlementMethod::Cash->value,
        'cash_amount' => 0,
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 1,
            'unit_price' => 1_000_000,
            'tax_rate' => 10,
            'discount' => 0,
        ]],
    ]);

    $invoice = Invoice::query()->whereBelongsTo($user)->firstOrFail();

    $response->assertRedirect(route('invoices.show', $invoice));
    expect($invoice->settlement_method)->toBe(SettlementMethod::Cash)
        ->and((float) $invoice->cash_amount)->toBe(1_000_000.0);
});

it('hides unit prices from the goods list', function () {
    $user = User::factory()->create();
    Good::factory()->for($user)->create(['unit_price' => 7_654_321]);

    $this->actingAs($user)
        ->get(route('goods.index'))
        ->assertOk()
        ->assertDontSee('قیمت واحد')
        ->assertDontSee('7,654,321');
});

it('does not render a unit price field in the goods editor', function () {
    $user = User::factory()->create();
    $good = Good::factory()->for($user)->create(['unit_price' => 7_654_321]);

    $this->actingAs($user)
        ->get(route('goods.edit', $good))
        ->assertOk()
        ->assertDontSee('name="unit_price"', false)
        ->assertDontSee('قیمت واحد');
});

it('keeps the automatic tax rate hidden in the invoice editor', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create(['tax_rate' => 9.5]);
    $invoice = Invoice::factory()->for($user)->for($customer)->create();
    $invoice->items()->create([
        'good_id' => $good->id,
        'description' => $good->name,
        'commodity_code' => $good->commodity_code,
        'quantity' => 1,
        'unit_price' => 1_000_000,
        'tax_rate' => 9.5,
        'discount' => 0,
        'subtotal' => 1_000_000,
        'tax_amount' => 95_000,
        'total' => 1_095_000,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.edit', $invoice))
        ->assertOk()
        ->assertDontSee('مالیات ٪')
        ->assertSee('type="hidden" value="10" data-field="tax_rate"', false)
        ->assertSee('name="items[0][tax_rate]" type="hidden" value="9.5"', false);
});

it('accepts a jalali invoice date and stores its gregorian equivalent', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $this->actingAs($user)->post(route('invoices.store'), [
        'customer_id' => $customer->id,
        'number' => 'INV-JALALI-0001',
        'invoice_date_jalali' => JalaliDate::format(today('Asia/Tehran')),
        'items' => [[
            'good_id' => $good->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'tax_rate' => 10,
            'discount' => 0,
        ]],
    ])->assertRedirect();

    expect(Invoice::whereBelongsTo($user)->firstOrFail()->invoice_date->format('Y-m-d'))->toBe(today('Asia/Tehran')->format('Y-m-d'));
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

it('renders invoice status and type filters as tabs and preserves filtering', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    Invoice::factory()->for($user)->for($customer)->create(['number' => 'DRAFT-TAB', 'status' => InvoiceStatus::Draft]);
    Invoice::factory()->for($user)->for($customer)->create(['number' => 'CONFIRMED-TAB', 'status' => InvoiceStatus::Confirmed]);

    $this->actingAs($user)
        ->get(route('invoices.index', ['status' => InvoiceStatus::Confirmed->value]))
        ->assertOk()
        ->assertSee('فیلتر وضعیت صورتحساب', false)
        ->assertSee('همه وضعیت‌ها')
        ->assertSee('نوع صورتحساب:')
        ->assertDontSee('<select name="status"', false)
        ->assertDontSee('<select name="type"', false)
        ->assertSee('CONFIRMED-TAB')
        ->assertDontSee('DRAFT-TAB');
});

it('filters invoice columns and shows unsynchronized buyer reactions', function () {
    $user = User::factory()->create();
    $firstCustomer = Customer::factory()->for($user)->create(['name' => 'مشتری اول']);
    $secondCustomer = Customer::factory()->for($user)->create(['name' => 'مشتری دوم']);
    Invoice::factory()->for($user)->for($firstCustomer)->create([
        'number' => 'REJECTED-BUYER',
        'buyer_status' => BuyerStatus::Rejected,
        'total' => 2_000_000,
    ]);
    Invoice::factory()->for($user)->for($secondCustomer)->create([
        'number' => 'NOT-SYNCED-BUYER',
        'buyer_status' => null,
        'total' => 500_000,
    ]);

    $this->actingAs($user)
        ->get(route('invoices.index', [
            'customer_id' => $firstCustomer->id,
            'buyer_status' => BuyerStatus::Rejected->value,
            'minimum_total' => 1_000_000,
        ]))
        ->assertOk()
        ->assertSee('invoice-column-filters', false)
        ->assertSee('REJECTED-BUYER')
        ->assertSee('رد خریدار')
        ->assertDontSee('NOT-SYNCED-BUYER');

    $this->actingAs($user)
        ->get(route('invoices.index', ['buyer_status' => 'not_synced']))
        ->assertOk()
        ->assertSee('NOT-SYNCED-BUYER')
        ->assertSee('همگام‌سازی نشده')
        ->assertDontSee('REJECTED-BUYER');
});

it('moves selected invoices through send confirmation', function () {
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
        ->and($invoice->status)->toBe(InvoiceStatus::AwaitingConfirmation)
        ->and($invoice->moadian_status)->toBe(MoadianStatus::Pending);
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

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::AwaitingConfirmation)
        ->and($invoice->last_inquired_at)->not->toBeNull()
        ->and($invoice->error_message)->toContain('خطای موقت سامانه مودیان');
});
