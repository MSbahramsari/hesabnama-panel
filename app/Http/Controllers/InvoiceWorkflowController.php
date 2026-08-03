<?php

namespace App\Http\Controllers;

use App\Actions\InquireInvoiceAction;
use App\Actions\SubmitInvoicesAction;
use App\Contracts\TaxPlatformGateway;
use App\Enums\InvoiceStatus;
use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Http\Requests\SendInvoicesRequest;
use App\Http\Requests\UpdateBuyerStatusRequest;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InvoiceWorkflowController extends Controller
{
    public function send(
        SendInvoicesRequest $request,
        SubmitInvoicesAction $action,
        TaxPlatformGateway $gateway,
    ): RedirectResponse {
        $invoices = Invoice::query()
            ->whereBelongsTo($request->user())
            ->whereIn('id', $request->validated('invoice_ids'))
            ->with(['customer', 'items.good'])
            ->get();

        $invoices->each(fn (Invoice $invoice) => Gate::authorize('send', $invoice));
        $count = $action->handle($invoices);
        $failedCount = $invoices->where('status', InvoiceStatus::MoadianError)->count();

        if ($count === 0) {
            return back()->with('error', 'هیچ صورتحسابی ارسال نشد. جزئیات خطا را در صفحه صورتحساب بررسی کنید.');
        }

        $destination = $gateway->isDemo() ? 'درگاه آزمایشی مودیان' : 'سامانه مودیان';
        $message = "{$count} فاکتور با موفقیت به {$destination} ارسال شد.";

        if ($failedCount > 0) {
            $message .= " {$failedCount} مورد ناموفق بود و برای بررسی علامت‌گذاری شد.";
        }

        return back()->with('success', $message);
    }

    public function confirm(Invoice $invoice, TaxPlatformGateway $gateway): RedirectResponse
    {
        abort_unless($gateway->isDemo(), 404);
        Gate::authorize('confirm', $invoice);
        $invoice->update(['status' => InvoiceStatus::Confirmed, 'confirmed_at' => now()]);

        return back()->with('success', 'پاسخ تأیید آزمایشی مودیان ثبت شد.');
    }

    public function inquire(
        Invoice $invoice,
        InquireInvoiceAction $action,
        TaxPlatformGateway $gateway,
    ): RedirectResponse {
        abort_if($gateway->isDemo(), 404);
        Gate::authorize('inquire', $invoice);

        try {
            $action->handle($invoice);
        } catch (MoadianConfigurationException|MoadianApiException $exception) {
            $invoice->update([
                'status' => InvoiceStatus::MoadianError,
                'last_inquired_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'آخرین وضعیت صورتحساب از سامانه مودیان دریافت شد.');
    }

    public function buyerStatus(UpdateBuyerStatusRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('updateBuyerStatus', $invoice);
        $invoice->update($request->validated());

        return back()->with('success', 'وضعیت خریدار به‌روزرسانی شد.');
    }
}
