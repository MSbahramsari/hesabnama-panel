<?php

use App\Contracts\TaxPlatformGateway;
use App\Enums\BuyerStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\MoadianApiException;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
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
