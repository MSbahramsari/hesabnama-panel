<?php

use App\Models\StuffCatalogItem;
use App\Models\User;

it('searches the catalog by description and filters the results', function () {
    $user = User::factory()->create();
    $matchingItem = StuffCatalogItem::factory()->create([
        'item_id' => '2330000000001',
        'description' => 'خدمات حسابداری و حسابرسی مالی',
        'type' => 'عمومی خدمت',
        'vat' => 10,
        'source_hash' => hash('sha256', 'matching-item'),
    ]);
    StuffCatalogItem::factory()->create([
        'item_id' => '1110000000002',
        'description' => 'رایانه قابل حمل',
        'type' => 'عمومی داخل',
        'vat' => 10,
        'source_hash' => hash('sha256', 'other-item'),
    ]);

    $response = $this->actingAs($user)->get(route('goods.create', [
        'catalog_query' => 'حسابداری',
        'catalog_type' => 'عمومی خدمت',
        'catalog_vat' => '10',
    ]));

    $response
        ->assertOk()
        ->assertSee($matchingItem->item_id)
        ->assertSee($matchingItem->description)
        ->assertDontSee('رایانه قابل حمل');
});

it('prefills the good form from the selected catalog row', function () {
    $user = User::factory()->create();
    $item = StuffCatalogItem::factory()->create([
        'item_id' => '2330000000003',
        'description' => 'خدمات تنظیم اظهارنامه مالیاتی',
        'type' => 'اختصاصی خدمت',
        'vat' => 10,
        'effective_date' => '1405/01/01',
        'source_hash' => hash('sha256', 'selected-item'),
    ]);

    $this->actingAs($user)
        ->get(route('goods.create', ['catalog_item' => $item->id]))
        ->assertOk()
        ->assertSee($item->item_id)
        ->assertSee($item->description)
        ->assertSee('اختصاصی خدمت')
        ->assertSee('value="10"', false);
});

it('imports an official-style csv and keeps repeated imports idempotent', function () {
    $path = tempnam(sys_get_temp_dir(), 'stuff-catalog-');
    $csv = <<<'CSV'
شناسه,نام کالا و خدمات,نوع,ارزش افزوده,تاریخ ایجاد,تاریخ اجرا,تاریخ انقضا,تاریخ بروزرسانی
۲۳۳۰۰۰۰۰۰۰۰۰۴,خدمات مشاوره مالیاتی,عمومی خدمت,۱۰٪,۱۴۰۴/۱۲/۰۱,۱۴۰۵/۰۱/۰۱,,۱۴۰۵/۰۱/۰۲
CSV;

    file_put_contents($path, $csv);

    try {
        $this->artisan('stuff:import', ['files' => [$path]])->assertSuccessful();
        $this->artisan('stuff:import', ['files' => [$path]])->assertSuccessful();
    } finally {
        @unlink($path);
    }

    expect(StuffCatalogItem::query()->count())->toBe(1);

    $this->assertDatabaseHas('stuff_catalog_items', [
        'item_id' => '2330000000004',
        'description' => 'خدمات مشاوره مالیاتی',
        'type' => 'عمومی خدمت',
        'vat' => 10,
        'effective_date' => '1405/01/01',
    ]);
});
