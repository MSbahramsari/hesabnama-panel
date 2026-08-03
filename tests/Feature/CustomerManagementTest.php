<?php

use App\Models\Customer;
use App\Models\User;

it('allows a permitted user to create a customer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('customers.store'), [
        'economic_code' => '411111111111',
        'national_id' => '14001234567',
        'name' => 'شرکت آزمون',
        'type' => 'legal',
        'address' => 'تهران',
        'postal_code' => '1991912345',
        'phone' => '02188776655',
        'is_active' => true,
    ]);

    $customer = Customer::whereBelongsTo($user)->firstOrFail();
    $response->assertRedirect(route('customers.edit', $customer));
    $this->assertModelExists($customer);
});

it('prevents users from editing another account customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($user)->get(route('customers.edit', $customer))->assertForbidden();
});
