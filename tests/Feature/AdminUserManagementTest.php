<?php

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\User;

it('allows an admin to provision a licensed user', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'کاربر تازه',
        'email' => 'new-user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::Member->value,
        'plan' => Plan::Business->value,
        'permissions' => ['customers', 'goods', 'invoices'],
        'license_expires_at' => now()->addYear()->format('Y-m-d'),
        'is_active' => true,
    ])->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'new-user@example.com')->firstOrFail();
    expect($user->permissions)->toBe(['customers', 'goods', 'invoices'])
        ->and($user->hasActiveLicense())->toBeTrue();
});

it('forbids a member from the admin user panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});
