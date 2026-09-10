<?php

use App\Enums\BuyerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\SettlementMethod;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;

it('duplicates any invoice as a clean editable draft', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();
    $source = Invoice::factory()->for($user)->for($customer)->create([
        'number' => 'SOURCE-INVOICE',
        'invoice_type' => InvoiceType::Correction,
        'settlement_method' => SettlementMethod::Mixed,
        'cash_amount' => 400_000,
        'status' => InvoiceStatus::Confirmed,
        'buyer_status' => BuyerStatus::Accepted,
        'tax_id' => 'ABC1230000000000000001',
        'submission_uid' => '8a00f17a-bd35-46bc-ae52-3f61fab868c2',
        'description' => 'توضیحات قابل کپی',
    ]);
    $source->items()->create([
        'good_id' => $good->id,
        'description' => $good->name,
        'commodity_code' => $good->commodity_code,
        'quantity' => 6,
        'unit_price' => 1_000_000,
        'tax_rate' => 10,
        'discount' => 100_000,
        'subtotal' => 6_000_000,
        'tax_amount' => 590_000,
        'total' => 6_490_000,
    ]);

    $this->actingAs($user)
        ->post(route('invoices.duplicate', $source))
        ->assertRedirect();

    $copy = Invoice::query()->whereBelongsTo($user)->whereKeyNot($source->id)->firstOrFail();

    expect($copy->status)->toBe(InvoiceStatus::Draft)
        ->and($copy->invoice_type)->toBe(InvoiceType::Original)
        ->and($copy->customer_id)->toBe($source->customer_id)
        ->and($copy->description)->toBe($source->description)
        ->and($copy->invoice_date->isToday())->toBeTrue()
        ->and($copy->tax_id)->toBeNull()
        ->and($copy->submission_uid)->toBeNull()
        ->and($copy->buyer_status)->toBeNull()
        ->and($copy->items)->toHaveCount(1)
        ->and((float) $copy->items->first()->quantity)->toBe(6.0)
        ->and((float) $copy->items->first()->unit_price)->toBe(1_000_000.0);

    $this->actingAs($user)
        ->get(route('invoices.show', $copy))
        ->assertOk()
        ->assertSee('کپی صورتحساب')
        ->assertSee('>6<', false)
        ->assertDontSee('6.000');
});

it('prevents duplicating another users invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();

    $this->actingAs($user)
        ->post(route('invoices.duplicate', $invoice))
        ->assertForbidden();
});
