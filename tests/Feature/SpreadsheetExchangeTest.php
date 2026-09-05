<?php

use App\Enums\SettlementMethod;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Spreadsheet\GoodSpreadsheetService;
use Illuminate\Http\UploadedFile;

it('imports customers and invoices from csv files', function () {
    $user = User::factory()->create();
    $customerCsv = implode("\n", [
        'کد اقتصادی,شناسه ملی,نام مشتری,نوع شخصیت,نشانی,کد پستی,شماره تماس,وضعیت',
        '411111111111,14001234567,شرکت آزمون اکسل,حقوقی,تهران,1234567890,02112345678,فعال',
    ]);

    $this->actingAs($user)->post(route('customers.import'), [
        'spreadsheet' => UploadedFile::fake()->createWithContent('customers.csv', $customerCsv),
    ])->assertRedirect()->assertSessionHas('success');

    $customer = Customer::query()->whereBelongsTo($user)->firstOrFail();
    expect($customer->name)->toBe('شرکت آزمون اکسل');

    $good = Good::factory()->for($user)->create([
        'commodity_code' => '12345678',
        'unit_price' => 1_000_000,
        'tax_rate' => 10,
    ]);
    $invoiceCsv = implode("\n", [
        'شماره داخلی,تاریخ صورتحساب,کد اقتصادی مشتری,روش تسویه,مبلغ نقدی,شناسه کالا/خدمت,تعداد,قیمت واحد,نرخ مالیات,تخفیف,توضیحات,نوع صورتحساب,شماره مالیاتی,وضعیت',
        "INV-XLSX-1,1405/06/14,{$customer->economic_code},نسیه,0,{$good->commodity_code},2,1000000,10,0,ورود از اکسل,اصلی,,",
        "INV-XLSX-1,1405/06/14,{$customer->economic_code},نسیه,0,{$good->commodity_code},1,500000,10,0,ورود از اکسل,اصلی,,",
    ]);

    $this->actingAs($user)->post(route('invoices.import'), [
        'spreadsheet' => UploadedFile::fake()->createWithContent('invoices.csv', $invoiceCsv),
    ])->assertRedirect()->assertSessionHas('success');

    $invoice = Invoice::query()->whereBelongsTo($user)->where('number', 'INV-XLSX-1')->firstOrFail();
    expect($invoice->items)->toHaveCount(2)
        ->and($invoice->settlement_method)->toBe(SettlementMethod::Credit)
        ->and((float) $invoice->total)->toBe(2_750_000.0);
});

it('writes and reads a real xlsx file without retaining the upload', function () {
    $sourceUser = User::factory()->create();
    $targetUser = User::factory()->create();
    $good = Good::factory()->for($sourceUser)->create([
        'commodity_code' => '87654321',
        'name' => 'قلم انتقالی اکسل',
    ]);
    $service = app(GoodSpreadsheetService::class);
    $response = $service->export($sourceUser);
    $path = $response->getFile()->getPathname();
    $upload = new UploadedFile($path, 'goods.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    $result = $service->import($targetUser, $upload);

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and(Good::query()->whereBelongsTo($targetUser)->where('commodity_code', $good->commodity_code)->value('name'))->toBe('قلم انتقالی اکسل');
});

it('exports all three operational datasets as xlsx downloads', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    foreach (['customers.export', 'goods.export', 'invoices.export'] as $routeName) {
        $this->get(route($routeName))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
});
