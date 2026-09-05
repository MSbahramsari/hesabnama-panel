<?php

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;

it('creates correction and cancellation drafts from a confirmed reference', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();
    $reference = Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::Confirmed,
        'tax_id' => 'ABC1230000000000000001',
    ]);
    $reference->items()->create([
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

    $this->actingAs($user)->post(route('invoices.correction', $reference))->assertRedirect();
    $correction = Invoice::query()->where('reference_invoice_id', $reference->id)->where('invoice_type', InvoiceType::Correction)->firstOrFail();

    expect($correction->status)->toBe(InvoiceStatus::Draft)
        ->and($correction->items)->toHaveCount(1)
        ->and($correction->customer_id)->toBe($reference->customer_id);

    $otherGood = Good::factory()->for($user)->create();
    $this->actingAs($user)->put(route('invoices.update', $correction), [
        'customer_id' => $reference->customer_id,
        'number' => $correction->number,
        'invoice_date' => today()->format('Y-m-d'),
        'settlement_method' => 'cash',
        'items' => [[
            'good_id' => $otherGood->id,
            'quantity' => 1,
            'unit_price' => 1_000_000,
            'tax_rate' => 10,
            'discount' => 0,
        ]],
    ])->assertSessionHasErrors('items.0.good_id');

    $this->actingAs($user)->post(route('invoices.cancellation', $reference))->assertRedirect();
    $cancellation = Invoice::query()->where('reference_invoice_id', $reference->id)->where('invoice_type', InvoiceType::Cancellation)->firstOrFail();

    expect($cancellation->isEditable())->toBeFalse()
        ->and($cancellation->items)->toHaveCount(1);

    $this->actingAs($user)->get(route('invoices.show', $reference))
        ->assertSuccessful()
        ->assertSee($correction->number)
        ->assertSee($cancellation->number)
        ->assertSee('صورتحساب‌های ارجاعی');
});

it('does not allow another user or a draft invoice to be adjusted', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $customer = Customer::factory()->for($owner)->create();
    $draft = Invoice::factory()->for($owner)->for($customer)->create(['status' => InvoiceStatus::Draft]);

    $this->actingAs($owner)->post(route('invoices.correction', $draft))->assertForbidden();
    $this->actingAs($otherUser)->post(route('invoices.cancellation', $draft))->assertForbidden();
});
