<?php

use App\Jobs\ImportStuffCatalog;
use App\Models\StuffCatalogImport;
use App\Models\StuffCatalogItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('allows administrators to view the catalog update page', function () {
    $admin = User::factory()->admin()->create();
    StuffCatalogItem::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get(route('admin.stuff-catalog.index'))
        ->assertOk()
        ->assertSee('بروزرسانی کاتالوگ کالا و خدمات')
        ->assertSee('ورود فایل رسمی جدید')
        ->assertSee('data-catalog-import-form', false)
        ->assertSee('data-upload-progress', false);
});

it('prevents regular users from managing the shared catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.stuff-catalog.index'))
        ->assertForbidden();
});

it('queues ajax uploads and exposes their processing progress', function () {
    Storage::fake('local');
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $file = UploadedFile::fake()->createWithContent('goods.csv', <<<'CSV'
ID,DescriptionOfID,VAT,Taxable,RunDate,ExpirationDate,Type,CreateDate,LastEditDate
2330000000010,خدمات حسابداری,10,مشمول,1405/01/01,,عمومی خدمت,1404/12/01,1405/01/02
CSV);

    $response = $this->actingAs($admin)
        ->withHeader('X-Requested-With', 'XMLHttpRequest')
        ->post(route('admin.stuff-catalog.store'), ['catalog_files' => [$file]]);

    $history = StuffCatalogImport::query()->sole();

    $response->assertAccepted()
        ->assertJsonPath('imports.0.id', $history->id)
        ->assertJsonPath('imports.0.status_url', route('admin.stuff-catalog.imports.show', $history));
    expect($history->status)->toBe(StuffCatalogImport::STATUS_QUEUED);

    Queue::assertPushed(ImportStuffCatalog::class, function (ImportStuffCatalog $job): bool {
        Storage::disk('local')->assertExists($job->storedPath);

        return $job->importId === StuffCatalogImport::query()->sole()->id;
    });

    $history->update([
        'status' => StuffCatalogImport::STATUS_PROCESSING,
        'progress_percent' => 42,
        'processed_rows' => 12500,
    ]);

    $this->actingAs($admin)
        ->getJson(route('admin.stuff-catalog.imports.show', $history))
        ->assertOk()
        ->assertJson([
            'status' => StuffCatalogImport::STATUS_PROCESSING,
            'progress_percent' => 42,
            'processed_rows' => 12500,
        ]);
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
        'progress_percent' => 100,
        'new_rows' => 1,
        'updated_rows' => 0,
    ]);
    $this->assertDatabaseHas('stuff_catalog_imports', [
        'file_name' => 'goods-updated.csv',
        'status' => StuffCatalogImport::STATUS_COMPLETED,
        'progress_percent' => 100,
        'new_rows' => 0,
        'updated_rows' => 1,
    ]);
    expect(Storage::disk('local')->allFiles('stuff-catalog-imports'))->toBe([]);
});
