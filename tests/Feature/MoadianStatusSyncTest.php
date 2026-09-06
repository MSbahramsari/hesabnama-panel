<?php

use App\Contracts\TaxPlatformGateway;
use App\Enums\BuyerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MoadianStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\InquiryResult;
use App\Services\Spreadsheet\MoadianSalesReportService;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

it('stores the official moadian processing result returned by uid inquiry', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::AwaitingConfirmation,
        'submission_uid' => '8a00f17a-bd35-46bc-ae52-3f61fab868c2',
        'reference_number' => '967072eb-203e-428e-b9bb-6d2efdb9d356',
    ]);

    mock(TaxPlatformGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('inquire')->once()->andReturn(new InquiryResult(
            'SUCCESS',
            'SUCCESS',
            'd4c0e7e6-d42e-11ec-9d64-0242ac120002',
            'RECEIVE_INVOICE_CONFIRM',
        ));
    });

    $this->artisan('moadian:sync-invoices')->assertSuccessful();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Confirmed)
        ->and($invoice->moadian_status)->toBe(MoadianStatus::Success)
        ->and($invoice->moadian_tax_result)->toBe('SUCCESS')
        ->and($invoice->moadian_confirmation_reference_id)->toBe('d4c0e7e6-d42e-11ec-9d64-0242ac120002')
        ->and($invoice->moadian_packet_type)->toBe('RECEIVE_INVOICE_CONFIRM')
        ->and($invoice->confirmed_at)->not->toBeNull();
});

it('imports actual buyer reactions from the official moadian sales report', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->for($user)->create();
    $invoice = Invoice::factory()->for($user)->for($customer)->create([
        'status' => InvoiceStatus::Confirmed,
        'tax_id' => 'ABC1230000000000000001',
    ]);
    $file = UploadedFile::fake()->createWithContent(
        'moadian-sales.csv',
        "شماره منحصر به فرد مالیاتی,وضعیت واکنش خریدار\nABC1230000000000000001,تأیید سیستمی\nUNKNOWN,رد شده\n",
    );

    $result = app(MoadianSalesReportService::class)->import($user, $file);

    expect($result)->toBe(['updated' => 1, 'unmatched' => 1, 'ignored' => 0])
        ->and($invoice->refresh()->buyer_status)->toBe(BuyerStatus::SystemAccepted)
        ->and($invoice->buyer_status_source)->toBe('moadian_sales_report')
        ->and($invoice->buyer_status_updated_at)->not->toBeNull();
});

it('does not expose a manual buyer status endpoint', function () {
    expect(app('router')->getRoutes()->getByName('invoices.buyer_status'))->toBeNull();
});
