<?php

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use phpseclib3\Crypt\RSA;

it('allows an admin to provision a licensed user', function () {
    $admin = User::factory()->admin()->create();
    $privateKey = RSA::createKey(2048)->toString('PKCS8');

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'کاربر تازه',
        'email' => 'new-user@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::Member->value,
        'plan' => Plan::Business->value,
        'permissions' => ['customers', 'goods', 'invoices'],
        'license_expires_at_jalali' => '1406/05/24',
        'is_active' => true,
        'taxpayer_name' => 'شرکت کاربر تازه',
        'taxpayer_type' => 'legal',
        'national_id' => '14001234567',
        'economic_code' => '411111111111',
        'fiscal_id' => 'ABC123',
        'branch_code' => '1',
        'private_key' => UploadedFile::fake()->createWithContent('private.pem', $privateKey),
    ]);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->with('taxpayerProfile')->where('email', 'new-user@example.com')->firstOrFail();
    $storedPrivateKey = DB::table('taxpayer_profiles')->where('user_id', $user->id)->value('private_key');

    expect($user->permissions)->toBe(['customers', 'goods', 'invoices'])
        ->and($user->hasActiveLicense())->toBeTrue()
        ->and($user->license_expires_at->format('Y-m-d'))->toBe('2027-08-15')
        ->and($user->taxpayerProfile->fiscal_id)->toBe('ABC123')
        ->and($user->taxpayerProfile->private_key)->toBe($privateKey)
        ->and($storedPrivateKey)->not->toBe($privateKey);
});

it('requires taxpayer credentials when provisioning a member', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Incomplete User',
        'email' => 'incomplete@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::Member->value,
        'plan' => Plan::Business->value,
        'permissions' => ['customers'],
        'license_expires_at' => now()->addYear()->format('Y-m-d'),
        'is_active' => true,
    ])->assertInvalid(['taxpayer_name', 'taxpayer_type', 'national_id', 'economic_code', 'fiscal_id', 'private_key']);
});

it('allows provisioning an administrator without a taxpayer profile', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Second Administrator',
        'email' => 'second-admin@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role' => UserRole::Admin->value,
        'plan' => Plan::Enterprise->value,
        'permissions' => [],
        'is_active' => true,
    ])->assertRedirect(route('admin.users.index'));

    $createdAdministrator = User::query()->where('email', 'second-admin@example.com')->firstOrFail();

    expect($createdAdministrator->isAdmin())->toBeTrue()
        ->and($createdAdministrator->taxpayerProfile)->toBeNull();
});

it('forbids a member from the admin user panel', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

it('renders the taxpayer fields on the account creation form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertSuccessful()
        ->assertSee('اطلاعات اتصال به سامانه مودیان')
        ->assertSee('data-jalali-date', false)
        ->assertDontSee('type="date"', false);
});
