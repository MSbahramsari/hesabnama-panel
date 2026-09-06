<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportMoadianSalesReportRequest;
use App\Http\Requests\ImportSpreadsheetRequest;
use App\Models\Customer;
use App\Models\Good;
use App\Models\Invoice;
use App\Services\Spreadsheet\CustomerSpreadsheetService;
use App\Services\Spreadsheet\GoodSpreadsheetService;
use App\Services\Spreadsheet\InvoiceSpreadsheetService;
use App\Services\Spreadsheet\MoadianSalesReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DataExchangeController extends Controller
{
    public function exportCustomers(Request $request, CustomerSpreadsheetService $service): BinaryFileResponse
    {
        Gate::authorize('viewAny', Customer::class);

        return $service->export($request->user(), $request->string('q')->trim()->toString());
    }

    public function customerTemplate(CustomerSpreadsheetService $service): BinaryFileResponse
    {
        Gate::authorize('create', Customer::class);

        return $service->template();
    }

    public function importCustomers(ImportSpreadsheetRequest $request, CustomerSpreadsheetService $service): RedirectResponse
    {
        return $this->runImport($request, fn (UploadedFile $file): array => $service->import($request->user(), $file));
    }

    public function exportGoods(Request $request, GoodSpreadsheetService $service): BinaryFileResponse
    {
        Gate::authorize('viewAny', Good::class);

        return $service->export($request->user(), $request->string('q')->trim()->toString());
    }

    public function goodTemplate(GoodSpreadsheetService $service): BinaryFileResponse
    {
        Gate::authorize('create', Good::class);

        return $service->template();
    }

    public function importGoods(ImportSpreadsheetRequest $request, GoodSpreadsheetService $service): RedirectResponse
    {
        return $this->runImport($request, fn (UploadedFile $file): array => $service->import($request->user(), $file));
    }

    public function exportInvoices(Request $request, InvoiceSpreadsheetService $service): BinaryFileResponse
    {
        Gate::authorize('viewAny', Invoice::class);

        return $service->export($request->user(), $request->only(['q', 'status', 'type']));
    }

    public function invoiceTemplate(InvoiceSpreadsheetService $service): BinaryFileResponse
    {
        Gate::authorize('create', Invoice::class);

        return $service->template();
    }

    public function importInvoices(ImportSpreadsheetRequest $request, InvoiceSpreadsheetService $service): RedirectResponse
    {
        return $this->runImport($request, fn (UploadedFile $file): array => $service->import($request->user(), $file));
    }

    public function importMoadianSalesReport(
        ImportMoadianSalesReportRequest $request,
        MoadianSalesReportService $service,
    ): RedirectResponse {
        try {
            $result = $service->import($request->user(), $request->file('spreadsheet'));
        } catch (Throwable $exception) {
            return back()->with('error', 'همگام‌سازی واکنش خریداران انجام نشد: '.$exception->getMessage());
        }

        $message = number_format($result['updated']).' واکنش رسمی خریدار به‌روزرسانی شد.';

        if ($result['unmatched'] > 0) {
            $message .= ' '.number_format($result['unmatched']).' شماره مالیاتی در حساب‌نما پیدا نشد.';
        }

        if ($result['ignored'] > 0) {
            $message .= ' '.number_format($result['ignored']).' ردیف فاقد وضعیت قابل‌شناسایی بود.';
        }

        return back()->with('success', $message);
    }

    /** @param callable(UploadedFile): array{created: int, updated: int, errors: array<int, string>} $importer */
    private function runImport(ImportSpreadsheetRequest $request, callable $importer): RedirectResponse
    {
        try {
            $result = $importer($request->file('spreadsheet'));
        } catch (Throwable $exception) {
            return back()->with('error', 'پردازش فایل انجام نشد: '.$exception->getMessage());
        }

        $message = number_format($result['created']).' مورد جدید ثبت و '.number_format($result['updated']).' مورد به‌روزرسانی شد.';

        if ($result['errors'] !== []) {
            $message .= ' '.number_format(count($result['errors'])).' ردیف نیازمند اصلاح است.';
        }

        return back()
            ->with('success', $message)
            ->with('import_errors', array_slice($result['errors'], 0, 20));
    }
}
