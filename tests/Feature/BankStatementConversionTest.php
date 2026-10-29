<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('converts xlsx bank transactions to balanced accounting voucher rows', function () {
    $user = User::factory()->create();
    $source = tempnam(sys_get_temp_dir(), 'bank-statement-').'.xlsx';
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['ردیف', 'تاریخ', 'شرح', 'مبلغ گردش بدهکار', 'مبلغ گردش بستانکار', 'شماره پیگیری'],
        [1, '1405/07/01', 'خرید خدمات', 1_500_000, 0, 'TRX-1'],
        [2, '1405/07/01', 'واریز مشتری', 0, 2_000_000, 'TRX-2'],
    ]);
    (new Xlsx($spreadsheet))->save($source);
    $spreadsheet->disconnectWorksheets();

    $response = $this->actingAs($user)->post(route('bank-statements.convert'), [
        'statement' => new UploadedFile($source, 'صورتحساب بانک ملت.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        'bank_moein_code' => '111001',
        'bank_tafsili_code' => '0501',
        'counterpart_moein_code' => '999001',
        'counterpart_tafsili_code' => '0001',
        'starting_voucher_number' => 21,
        'grouping' => 'daily',
        'document_description' => 'ثبت گردش آزمایشی',
    ]);

    $response->assertSuccessful()->assertDownload();
    $output = IOFactory::load($response->baseResponse->getFile()->getPathname());
    $outputSheet = $output->getActiveSheet();
    $rows = $outputSheet->toArray();

    expect($rows)->toHaveCount(5)
        ->and($rows[0][0])->toBe('نوع قلم')
        ->and($rows[1][0])->toBe('VoucherItem')
        ->and((int) $rows[1][1])->toBe(21)
        ->and((float) $outputSheet->getCell('G2')->getValue())->toBe(1_500_000.0)
        ->and((float) $outputSheet->getCell('H3')->getValue())->toBe(1_500_000.0)
        ->and((float) $outputSheet->getCell('G4')->getValue())->toBe(2_000_000.0)
        ->and((float) $outputSheet->getCell('H5')->getValue())->toBe(2_000_000.0)
        ->and((float) $outputSheet->getCell('G2')->getValue() + (float) $outputSheet->getCell('G4')->getValue())
        ->toBe((float) $outputSheet->getCell('H3')->getValue() + (float) $outputSheet->getCell('H5')->getValue());

    $output->disconnectWorksheets();
    @unlink($source);
});

it('reads legacy xls statements with a transaction type and amount column', function () {
    $user = User::factory()->create();
    $source = tempnam(sys_get_temp_dir(), 'bank-statement-').'.xls';
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['ردیف', 'تاریخ', 'نوع تراکنش', 'مبلغ تراکنش', 'شرح', 'شماره پیگیری'],
        [1, '1405/07/02', 'برداشت', 700_000, 'کارمزد خدمات', 'LEGACY-1'],
    ]);
    (new Xls($spreadsheet))->save($source);
    $spreadsheet->disconnectWorksheets();

    $this->actingAs($user)->post(route('bank-statements.convert'), [
        'statement' => new UploadedFile($source, 'صورتحساب بانک ملی.xls', 'application/vnd.ms-excel', null, true),
        'bank_moein_code' => '111001',
        'counterpart_moein_code' => '999001',
        'starting_voucher_number' => 1,
        'grouping' => 'transaction',
    ])->assertSuccessful()->assertDownload();

    @unlink($source);
});

it('only shows and opens bank conversion for users with its explicit permission', function () {
    $authorizedUser = User::factory()->create(['permissions' => ['bank_statements']]);
    $unauthorizedUser = User::factory()->create(['permissions' => ['customers', 'invoices']]);

    $this->actingAs($authorizedUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('bank-statements.index'), false)
        ->assertDontSee(route('invoices.index'), false);
    $this->actingAs($authorizedUser)->get(route('bank-statements.index'))->assertOk();

    $this->actingAs($unauthorizedUser)->get(route('bank-statements.index'))->assertForbidden();
    $this->actingAs($unauthorizedUser)->post(route('bank-statements.convert'))->assertForbidden();
});

it('shows the bank conversion tool to administrators', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('bank-statements.index'), false);
    $this->actingAs($admin)->get(route('bank-statements.index'))->assertOk();
});
