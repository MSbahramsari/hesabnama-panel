<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertBankStatementRequest;
use App\Services\Spreadsheet\AccountingVoucherSpreadsheet;
use App\Services\Spreadsheet\BankStatementParser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BankStatementController extends Controller
{
    public function index(): View
    {
        return view('bank-statements.index');
    }

    public function convert(
        ConvertBankStatementRequest $request,
        BankStatementParser $parser,
        AccountingVoucherSpreadsheet $spreadsheet,
    ): BinaryFileResponse|RedirectResponse {
        try {
            $conversion = $parser->parse($request->file('statement'));
        } catch (RuntimeException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return $spreadsheet->download($conversion, [
            'bank_moein_code' => $request->string('bank_moein_code')->trim()->toString(),
            'bank_tafsili_code' => $request->string('bank_tafsili_code')->trim()->toString() ?: null,
            'counterpart_moein_code' => $request->string('counterpart_moein_code')->trim()->toString(),
            'counterpart_tafsili_code' => $request->string('counterpart_tafsili_code')->trim()->toString() ?: null,
            'starting_voucher_number' => $request->integer('starting_voucher_number'),
            'grouping' => $request->string('grouping')->toString(),
            'document_description' => $request->string('document_description')->trim()->toString() ?: null,
        ]);
    }
}
