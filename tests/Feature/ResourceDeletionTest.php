<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;

it('deletes unused customers goods and draft invoices', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($user)->for($customer)->create();

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $invoice))
        ->assertRedirect(route('invoices.index'))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'))
        ->assertSessionHas('success');

    $this->actingAs($user)
        ->delete(route('goods.destroy', $good))
        ->assertRedirect(route('goods.index'))
        ->assertSessionHas('success');

    $this->assertModelMissing($invoice);
    $this->assertModelMissing($customer);
    $this->assertModelMissing($good);
});

it('keeps customers and goods that are used in an invoice', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($user)->for($customer)->create();

    $invoice->items()->create([
        'good_id' => $good->id,
        'description' => $good->name,
        'commodity_code' => $good->commodity_code,
        'quantity' => 1,
        'unit_price' => 1000000,
        'tax_rate' => 10,
        'discount' => 0,
        'subtotal' => 1000000,
        'tax_amount' => 100000,
        'total' => 1100000,
    ]);

    $this->actingAs($user)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.edit', $customer))
        ->assertSessionHas('error');

    $this->actingAs($user)
        ->delete(route('goods.destroy', $good))
        ->assertRedirect(route('goods.edit', $good))
        ->assertSessionHas('error');

    $this->assertModelExists($customer);
    $this->assertModelExists($good);
});

it('does not allow a submitted invoice to be deleted', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::Confirmed,
    ]);

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $invoice))
        ->assertForbidden();

    $this->assertModelExists($invoice);
});

it('shows navigation deletion and logout controls in the panel', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $good = Good::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('customers.create'))
        ->assertOk()
        ->assertSee('بازگشت');

    $this->actingAs($user)
        ->get(route('goods.create'))
        ->assertOk()
        ->assertSee('بازگشت');

    $this->actingAs($user)
        ->get(route('invoices.create'))
        ->assertOk()
        ->assertSee('بازگشت')
        ->assertSee('data-jalali-date', false)
        ->assertDontSee('type="date"', false);

    $this->actingAs($user)
        ->get(route('customers.edit', $customer))
        ->assertOk()
        ->assertSee('حذف مشتری')
        ->assertSee(route('customers.destroy', $customer), false);

    $this->actingAs($user)
        ->get(route('goods.edit', $good))
        ->assertOk()
        ->assertSee('حذف قلم')
        ->assertSee(route('goods.destroy', $good), false);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertSee(route('customers.destroy', $customer), false)
        ->assertSee('table-row-actions', false);

    $this->actingAs($user)
        ->get(route('goods.index'))
        ->assertOk()
        ->assertSee(route('goods.destroy', $good), false)
        ->assertSee('table-row-actions', false)
        ->assertSee('goods-table', false)
        ->assertSee('goods-name-icon', false);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('خروج از حساب')
        ->assertSee('topbar-page-icon', false)
        ->assertDontSee('صورتحساب جدید');
});
