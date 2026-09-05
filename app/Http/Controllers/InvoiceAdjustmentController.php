<?php

namespace App\Http\Controllers;

use App\Actions\CreateInvoiceAdjustmentAction;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class InvoiceAdjustmentController extends Controller
{
    public function correction(Invoice $invoice, CreateInvoiceAdjustmentAction $action): RedirectResponse
    {
        Gate::authorize('adjust', $invoice);
        $correction = $action->handle($invoice, InvoiceType::Correction);

        return redirect()->route('invoices.edit', $correction)
            ->with('success', 'پیش‌نویس صورتحساب اصلاحی ساخته شد؛ موارد مجاز را اصلاح و سپس ارسال کنید.');
    }

    public function cancellation(Invoice $invoice, CreateInvoiceAdjustmentAction $action): RedirectResponse
    {
        Gate::authorize('adjust', $invoice);
        $cancellation = $action->handle($invoice, InvoiceType::Cancellation);

        return redirect()->route('invoices.show', $cancellation)
            ->with('success', 'پیش‌نویس صورتحساب ابطالی ساخته شد و آماده ارسال است.');
    }
}
