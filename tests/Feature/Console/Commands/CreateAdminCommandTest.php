<?php

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the initial administrator with a hidden password prompt', function () {
    $this->artisan('admin:create', [
        'email' => 'OWNER@EXAMPLE.COM',
        '--name' => 'Production Owner',
    ])
        ->expectsQuestion('رمز عبور مدیر (حداقل ۱۲ کاراکتر)', 'A-very-strong-password')
        ->expectsQuestion('تکرار رمز عبور مدیر', 'A-very-strong-password')
        ->assertSuccessful();

    $administrator = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($administrator->name)->toBe('Production Owner')
        ->and($administrator->role)->toBe(UserRole::Admin)
        ->and($administrator->plan)->toBe(Plan::Enterprise)
        ->and($administrator->is_active)->toBeTrue()
        ->and(Hash::check('A-very-strong-password', $administrator->password))->toBeTrue();
});

it('refuses to overwrite an existing account', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('admin:create', [
        'email' => $user->email,
        '--name' => 'Another Owner',
    ])->assertFailed();

    expect(User::query()->where('email', $user->email)->count())->toBe(1);
});
