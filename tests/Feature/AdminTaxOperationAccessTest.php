<?php

use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;

it('shows administrators a system management dashboard without tax operations', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('داشبورد مدیریت')
        ->assertSee('مدیریت حساب‌نما')
        ->assertDontSee('<div class="nav-label">عملیات مالیاتی</div>', false)
        ->assertDontSee(route('customers.index'), false)
        ->assertDontSee(route('goods.index'), false)
        ->assertDontSee(route('invoices.index'), false);
});

it('forbids administrators from all taxpayer operation areas', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $customer = Customer::factory()->for($owner)->create();
    $good = Good::factory()->for($owner)->create();
    $invoice = Invoice::factory()->for($owner)->for($customer)->create();

    foreach ([
        route('customers.index'),
        route('customers.edit', $customer),
        route('goods.index'),
        route('goods.edit', $good),
        route('invoices.index'),
        route('invoices.show', $invoice),
    ] as $url) {
        $this->actingAs($admin)->get($url)->assertForbidden();
    }

    $this->actingAs($admin)->post(route('profile.moadian.test'))->assertForbidden();
});

it('keeps taxpayer operation areas available to licensed members', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('customers.index'))->assertOk();
    $this->actingAs($member)->get(route('goods.index'))->assertOk();
    $this->actingAs($member)->get(route('invoices.index'))->assertOk();
});
