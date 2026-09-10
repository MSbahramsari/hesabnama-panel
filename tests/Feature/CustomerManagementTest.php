<?php

use App\Models\Customer;
use App\Models\User;

it('allows a permitted user to create a customer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('customers.store'), [
        'economic_code' => '41111111111',
        'national_id' => '14001234567',
        'name' => 'شرکت آزمون',
        'type' => 'legal',
        'address' => 'تهران',
        'postal_code' => '1991912345',
        'phone' => '02188776655',
        'is_active' => true,
    ]);

    $customer = Customer::whereBelongsTo($user)->firstOrFail();
    $response->assertRedirect(route('customers.index'));
    $this->assertModelExists($customer);
});

it('normalizes Persian digits for a legal customer identity fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('customers.store'), [
        'economic_code' => '۴۱۱۱۱۱۱۱۱۱۱',
        'national_id' => '۱۴۰۰۱۲۳۴۵۶۷',
        'name' => 'شرکت حقوقی آزمون',
        'type' => 'legal',
        'postal_code' => '۱۹۹۱۹۱۲۳۴۵',
        'phone' => '۰۲۱۸۸۷۷۶۶۵۵',
        'is_active' => true,
    ])->assertRedirect(route('customers.index'));

    $customer = Customer::query()->whereBelongsTo($user)->firstOrFail();

    expect($customer->economic_code)->toBe('41111111111')
        ->and($customer->national_id)->toBe('14001234567')
        ->and($customer->postal_code)->toBe('1991912345')
        ->and($customer->phone)->toBe('02188776655');
});

it('accepts a ten digit national code as an individual customer identifier', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('customers.store'), [
        'economic_code' => '۰۲۰۰۱۰۰۷۵۰',
        'national_id' => '۰۲۰۰۱۰۰۷۵۰',
        'name' => 'مشتری حقیقی آزمون',
        'type' => 'individual',
        'is_active' => true,
    ])->assertRedirect(route('customers.index'));

    expect(Customer::query()->whereBelongsTo($user)->firstOrFail()->economic_code)
        ->toBe('0200100750');
});

it('prevents users from editing another account customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($user)->get(route('customers.edit', $customer))->assertForbidden();
});
