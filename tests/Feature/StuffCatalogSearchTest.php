<?php

use App\Contracts\TaxPlatformGateway;
use App\Models\Good;
use App\Models\StuffCatalogItem;
use App\Models\User;
use App\Services\OfficialStuffCatalogClient;
use Mockery\MockInterface;

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
        ->assertSee('#good-details', false)
        ->assertDontSee('رایانه قابل حمل');
});

it('does not show the external official reference action', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('goods.create'))
        ->assertOk()
        ->assertDontSee('مرجع رسمی سازمان مالیاتی');
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
        ->assertSee('data-selected-catalog-form', false)
        ->assertSee('قلم از کاتالوگ رسمی انتخاب شد')
        ->assertDontSee('کد واحد اندازه‌گیری مودیان')
        ->assertSee('name="measurement_unit_code" value="1627"', false)
        ->assertSee('value="10"', false);
});

it('assigns the measurement unit code automatically when a good is saved', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('goods.store'), [
        'commodity_code' => '2330000000005',
        'name' => 'خدمات حسابداری آزمایشی',
        'unit' => 'خدمت',
        'unit_price' => 1_000_000,
        'tax_rate' => 10,
        'is_active' => true,
    ])->assertRedirect();

    expect(Good::query()
        ->whereBelongsTo($user)
        ->where('commodity_code', '2330000000005')
        ->value('measurement_unit_code'))->toBe('1627');
});

it('shows numbered pagination for catalog search results', function () {
    $user = User::factory()->create();
    StuffCatalogItem::factory()->count(26)->create([
        'description' => 'خدمات حسابداری قابل جست‌وجو',
    ]);

    $this->actingAs($user)
        ->get(route('goods.create', ['catalog_query' => 'حسابداری']))
        ->assertOk()
        ->assertSee('aria-label="صفحه 2"', false)
        ->assertSee('از <strong>26</strong> مورد', false);
});

it('uses an exact catalog search as a direct official lookup', function () {
    $user = User::factory()->create();

    $officialCatalog = mock(OfficialStuffCatalogClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('lookup')->once()->with('2330002582524')->andReturnNull();
    });
    $this->app->instance(OfficialStuffCatalogClient::class, $officialCatalog);

    $gateway = mock(TaxPlatformGateway::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('lookupGood')
            ->once()
            ->withArgs(fn (User $lookupUser, string $code): bool => $lookupUser->is($user) && $code === '2330002582524')
            ->andReturn([
                'name' => 'خدمات مشاوره حسابداری مالی',
                'unit' => 'خدمت',
                'unit_price' => 0,
                'tax_rate' => 10,
                'measurement_unit_code' => '1627',
            ]);
        $mock->shouldReceive('isDemo')->once()->andReturnFalse();
    });
    $this->app->instance(TaxPlatformGateway::class, $gateway);

    $this->actingAs($user)
        ->get(route('goods.create', ['catalog_query' => '۲۳۳۰۰۰۲۵۸۲۵۲۴']))
        ->assertOk()
        ->assertSee('2330002582524')
        ->assertSee('خدمات مشاوره حسابداری مالی')
        ->assertSee('name="commodity_code"', false);
});

it('stores and displays an exact result received from the official stuff portal', function () {
    $user = User::factory()->create();

    $officialCatalog = mock(OfficialStuffCatalogClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('lookup')->once()->with('2330002582524')->andReturn([
            'item_id' => '2330002582524',
            'description' => 'خدمات مشاوره حسابداری مالی در زمینه خزانه داری',
            'type' => 'شناسه اختصاصی خدمت',
            'vat' => 10.0,
            'taxable' => 'مشمول',
            'source_created_date' => '1404-06-05',
            'effective_date' => '1403-02-02',
            'expiration_date' => null,
            'source_updated_date' => '1403-02-02',
        ]);
    });
    $this->app->instance(OfficialStuffCatalogClient::class, $officialCatalog);

    $gateway = mock(TaxPlatformGateway::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('lookupGood');
        $mock->shouldReceive('isDemo')->once()->andReturnFalse();
    });
    $this->app->instance(TaxPlatformGateway::class, $gateway);

    $this->actingAs($user)
        ->get(route('goods.create', ['catalog_query' => '2330002582524']))
        ->assertOk()
        ->assertSee('خدمات مشاوره حسابداری مالی در زمینه خزانه داری')
        ->assertSee('value="10"', false);

    $this->assertDatabaseHas('stuff_catalog_items', [
        'item_id' => '2330002582524',
        'type' => 'شناسه اختصاصی خدمت',
        'vat' => 10,
    ]);
});

it('imports an official-style csv and keeps repeated imports idempotent', function () {
    $path = tempnam(sys_get_temp_dir(), 'stuff-catalog-');
    $csv = <<<'CSV'
ID,DescriptionOfID,VAT,Taxable,RunDate,ExpirationDate,Type,CreateDate,LastEditDate
۲۳۳۰۰۰۰۰۰۰۰۰۴,خدمات مشاوره مالیاتی,۱۰٪,مشمول,۱۴۰۵/۰۱/۰۱,,عمومی خدمت,۱۴۰۴/۱۲/۰۱,۱۴۰۵/۰۱/۰۲
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
        'taxable' => 'مشمول',
        'effective_date' => '1405/01/01',
        'source_created_date' => '1404/12/01',
        'source_updated_date' => '1405/01/02',
    ]);
});
