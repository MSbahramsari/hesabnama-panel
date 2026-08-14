<?php

use App\Models\StuffCatalogImport;
use App\Models\StuffCatalogItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('allows administrators to view the catalog update page', function () {
    $admin = User::factory()->admin()->create();
    StuffCatalogItem::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.stuff-catalog.index'))
        ->assertOk()
        ->assertSee('بروزرسانی کاتالوگ کالا و خدمات')
        ->assertSee('ورود فایل رسمی جدید');
});

it('prevents regular users from managing the shared catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.stuff-catalog.index'))
        ->assertForbidden();
});

it('imports uploaded official files and records new and updated row counts', function () {
    $admin = User::factory()->admin()->create();
    $firstPath = tempnam(sys_get_temp_dir(), 'stuff-admin-first-');
    $secondPath = tempnam(sys_get_temp_dir(), 'stuff-admin-second-');
    $firstCsv = <<<'CSV'
ID,DescriptionOfID,VAT,Taxable,RunDate,ExpirationDate,Type,CreateDate,LastEditDate
2330000000010,خدمات حسابداری,10,مشمول,1405/01/01,,عمومی خدمت,1404/12/01,1405/01/02
CSV;
    $secondCsv = <<<'CSV'
ID,DescriptionOfID,VAT,Taxable,RunDate,ExpirationDate,Type,CreateDate,LastEditDate
2330000000010,خدمات حسابداری مالیاتی,10,مشمول,1405/01/01,,عمومی خدمت,1404/12/01,1405/02/01
CSV;

    file_put_contents($firstPath, $firstCsv);
    file_put_contents($secondPath, $secondCsv);

    try {
        $firstResponse = $this->actingAs($admin)->post(route('admin.stuff-catalog.store'), [
            'catalog_files' => [new UploadedFile($firstPath, 'goods.csv', 'text/csv', null, true)],
        ]);
        $secondResponse = $this->actingAs($admin)->post(route('admin.stuff-catalog.store'), [
            'catalog_files' => [new UploadedFile($secondPath, 'goods-updated.csv', 'text/csv', null, true)],
        ]);
    } finally {
        @unlink($firstPath);
        @unlink($secondPath);
    }

    $firstResponse->assertRedirect(route('admin.stuff-catalog.index'));
    $secondResponse->assertRedirect(route('admin.stuff-catalog.index'));

    expect(StuffCatalogItem::query()->count())->toBe(1)
        ->and(StuffCatalogImport::query()->count())->toBe(2);

    $this->assertDatabaseHas('stuff_catalog_items', [
        'item_id' => '2330000000010',
        'description' => 'خدمات حسابداری مالیاتی',
        'source_updated_date' => '1405/02/01',
    ]);
    $this->assertDatabaseHas('stuff_catalog_imports', [
        'file_name' => 'goods.csv',
        'status' => StuffCatalogImport::STATUS_COMPLETED,
        'new_rows' => 1,
        'updated_rows' => 0,
    ]);
    $this->assertDatabaseHas('stuff_catalog_imports', [
        'file_name' => 'goods-updated.csv',
        'status' => StuffCatalogImport::STATUS_COMPLETED,
        'new_rows' => 0,
        'updated_rows' => 1,
    ]);
});
