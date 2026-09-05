<?php

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;

it('reports effective sales after corrections and cancellations', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();
    $reference = Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::Confirmed,
        'total' => 1_100_000,
    ]);
    $correction = Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::Confirmed,
        'invoice_type' => InvoiceType::Correction,
        'reference_invoice_id' => $reference->id,
        'total' => 2_200_000,
    ]);
    $correction->items()->create([
        'good_id' => $good->id,
        'description' => $good->name,
        'commodity_code' => $good->commodity_code,
        'quantity' => 2,
        'unit_price' => 1_000_000,
        'tax_rate' => 10,
        'discount' => 0,
        'subtotal' => 2_000_000,
        'tax_amount' => 200_000,
        'total' => 2_200_000,
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSuccessful()
        ->assertViewHas('metrics', fn (array $metrics): bool => (float) $metrics['confirmed_total'] === 2_200_000.0)
        ->assertViewHas('topGoods', fn ($goods): bool => (float) $goods->first()->total_sum === 2_200_000.0)
        ->assertViewHas('periodSales');

    Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::Confirmed,
        'invoice_type' => InvoiceType::Cancellation,
        'reference_invoice_id' => $correction->id,
        'total' => 2_200_000,
    ]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertViewHas('metrics', fn (array $metrics): bool => (float) $metrics['confirmed_total'] === 0.0);
});
