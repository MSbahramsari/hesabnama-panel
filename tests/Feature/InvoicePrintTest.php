<?php

use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\TaxpayerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('renders a printable invoice with seller buyer items and print controls', function () {
    $user = User::factory()->create();
    TaxpayerProfile::factory()->for($user)->create([
        'taxpayer_name' => 'شرکت فروشنده آزمون',
        'address' => 'تهران، خیابان آزمون',
        'postal_code' => '1234567890',
        'phone' => '02112345678',
    ]);
    $customer = Customer::factory()->for($user)->create(['name' => 'شرکت خریدار آزمون']);
    $good = Good::factory()->for($user)->create(['name' => 'خدمت حسابداری']);
    $invoice = Invoice::factory()->for($user)->for($customer)->create(['number' => 'INV-PRINT-001']);
    $invoice->items()->create([
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

    $this->actingAs($user)
        ->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee('صورتحساب فروش کالا و خدمات')
        ->assertSee('شرکت فروشنده آزمون')
        ->assertSee('شرکت خریدار آزمون')
        ->assertSee('خدمت حسابداری')
        ->assertSee('window.print()', false);
});

it('does not expose another taxpayers printable invoice', function () {
    $owner = User::factory()->create();
    $customer = Customer::factory()->for($owner)->create();
    $invoice = Invoice::factory()->for($owner)->for($customer)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('invoices.print', $invoice))
        ->assertForbidden();
});

it('stores branding images privately and serves them only to their owner', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $profile = TaxpayerProfile::factory()->for($user)->create([
        'national_id' => '14001234567',
        'economic_code' => '41111111111',
    ]);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'taxpayer_name' => $profile->taxpayer_name,
        'taxpayer_type' => $profile->taxpayer_type,
        'national_id' => $profile->national_id,
        'economic_code' => $profile->economic_code,
        'fiscal_id' => $profile->fiscal_id,
        'branch_code' => $profile->branch_code,
        'company_logo' => UploadedFile::fake()->createWithContent('logo.png', $png),
        'stamp_signature' => UploadedFile::fake()->createWithContent('stamp.png', $png),
    ])->assertSessionHas('success');

    $profile->refresh();

    expect($profile->company_logo_path)->not->toBeNull()
        ->and($profile->stamp_signature_path)->not->toBeNull();
    Storage::disk('local')->assertExists($profile->company_logo_path);
    Storage::disk('local')->assertExists($profile->stamp_signature_path);

    $this->get(route('profile.branding', 'logo'))->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('profile.branding', 'logo'))
        ->assertNotFound();
});
