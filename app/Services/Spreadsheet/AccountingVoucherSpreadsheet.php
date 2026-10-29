<?php

namespace App\Services\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingVoucherSpreadsheet
{
    private const ISSUER_ENTITY_NAME = 'SG.Accounting.VoucherManagement.Common.DsVoucher, SG.Accounting.VoucherManagement.Common, Version=1.0.0.0, Culture=neutral, PublicKeyToken=null';

    /** @var array<int, string> */
    private const HEADERS = [
        'نوع قلم',
        'سند حسابداري ش فرعي',
        'سند حسابداري تاريخ',
        'سند حسابداري توضيحات',
        'اقلام سند حسابداري كد معين',
        'اقلام سند حسابداري كد تفصيلي',
        'اقلام سند حسابداري بدهكار',
        'اقلام سند حسابداري بستانكار',
        'اقلام سند حسابداري شرح',
        'اقلام سند حسابداري شماره پيگيري',
        'اقلام سند حسابداري تاريخ پيگيري',
        'اقلام سند حسابداري IssuerEntityName',
        'اقلام سند حسابداري IssuerEntityRef',
    ];

    /**
     * @param  array{bank_moein_code: string, bank_tafsili_code: ?string, counterpart_moein_code: string, counterpart_tafsili_code: ?string, starting_voucher_number: int, grouping: string, document_description: ?string}  $settings
     */
    public function download(BankStatementConversion $conversion, array $settings): BinaryFileResponse
    {
        $directory = storage_path('app/private/spreadsheet-exports');

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('ساخت مسیر موقت خروجی ممکن نیست.');
        }

        $path = tempnam($directory, 'bank-vouchers-');

        if ($path === false) {
            throw new RuntimeException('ساخت فایل موقت خروجی ممکن نیست.');
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Test');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(self::HEADERS, null, 'A1');
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCEFEF');
        $sheet->getStyle('A1:M1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->freezePane('A2');
        $sheet->getRowDimension(1)->setRowHeight(42);

        foreach (range('A', 'M') as $column) {
            $sheet->getColumnDimension($column)->setWidth(match ($column) {
                'D', 'I' => 34,
                'L' => 45,
                default => 18,
            });
        }

        $voucherNumbers = [];
        $nextVoucherNumber = $settings['starting_voucher_number'];
        $row = 2;

        foreach ($conversion->transactions as $index => $transaction) {
            $groupingKey = $settings['grouping'] === 'daily' ? $transaction['date'] : (string) $index;
            $voucherNumbers[$groupingKey] ??= $nextVoucherNumber++;
            $voucherNumber = $voucherNumbers[$groupingKey];
            $amount = $transaction['debit'] > 0 ? $transaction['debit'] : $transaction['credit'];
            $isWithdrawal = $transaction['debit'] > 0;
            $descriptionPrefix = trim((string) ($settings['document_description'] ?? ''));
            $voucherDescription = $descriptionPrefix !== '' ? $descriptionPrefix : 'ثبت گردش حساب '.$conversion->detectedBank;
            $itemDescription = ($isWithdrawal ? 'برداشت بانکی: ' : 'واریز بانکی: ').$transaction['description'];

            $firstAccount = $isWithdrawal
                ? [$settings['counterpart_moein_code'], $settings['counterpart_tafsili_code'], $amount, 0]
                : [$settings['bank_moein_code'], $settings['bank_tafsili_code'], $amount, 0];
            $secondAccount = $isWithdrawal
                ? [$settings['bank_moein_code'], $settings['bank_tafsili_code'], 0, $amount]
                : [$settings['counterpart_moein_code'], $settings['counterpart_tafsili_code'], 0, $amount];

            foreach ([$firstAccount, $secondAccount] as $account) {
                $values = [
                    'VoucherItem', $voucherNumber, $transaction['date'], $voucherDescription,
                    $account[0], $account[1] ?? '', $account[2], $account[3], $itemDescription,
                    $transaction['tracking_number'], $transaction['date'], self::ISSUER_ENTITY_NAME, 1,
                ];
                $sheet->fromArray($values, null, 'A'.$row);

                foreach (['A', 'C', 'E', 'F', 'J', 'K', 'L'] as $column) {
                    $sheet->setCellValueExplicit($column.$row, (string) $sheet->getCell($column.$row)->getValue(), DataType::TYPE_STRING);
                }

                $row++;
            }
        }

        $sheet->getStyle('G2:H'.($row - 1))->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('A1:M'.($row - 1))->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->setAutoFilter('A1:M'.($row - 1));

        (new Xls($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, 'accounting-vouchers-'.now()->format('Ymd-His').'.xls', [
            'Content-Type' => 'application/vnd.ms-excel',
        ])->deleteFileAfterSend(true);
    }
}
