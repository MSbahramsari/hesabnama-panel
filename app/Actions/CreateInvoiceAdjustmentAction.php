<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoiceAdjustmentAction
{
    public function handle(Invoice $referenceInvoice, InvoiceType $type): Invoice
    {
        if ($type === InvoiceType::Original || $referenceInvoice->invoice_type === InvoiceType::Cancellation) {
            throw ValidationException::withMessages(['invoice' => 'این صورتحساب نمی‌تواند به‌عنوان مرجع استفاده شود.']);
        }

        if ($referenceInvoice->status !== InvoiceStatus::Confirmed || blank($referenceInvoice->tax_id)) {
            throw ValidationException::withMessages(['invoice' => 'فقط صورتحساب تأییدشده دارای شماره مالیاتی می‌تواند مرجع باشد.']);
        }

        $hasConfirmedAdjustment = $referenceInvoice->adjustments()
            ->where('status', InvoiceStatus::Confirmed)
            ->exists();

        if ($hasConfirmedAdjustment) {
            throw ValidationException::withMessages(['invoice' => 'برای اصلاح یا ابطال، آخرین صورتحساب ارجاعی تأییدشده را به‌عنوان مرجع انتخاب کنید.']);
        }

        return DB::transaction(function () use ($referenceInvoice, $type): Invoice {
            $referenceInvoice->loadMissing('items');
            $prefix = $type === InvoiceType::Correction ? 'COR' : 'CAN';
            $sequence = $referenceInvoice->adjustments()->where('invoice_type', $type)->count() + 1;

            $invoice = Invoice::query()->create([
                'user_id' => $referenceInvoice->user_id,
                'customer_id' => $referenceInvoice->customer_id,
                'number' => "{$prefix}-{$referenceInvoice->id}-{$sequence}",
                'invoice_date' => today(),
                'description' => "ارجاع به صورتحساب {$referenceInvoice->number}",
                'invoice_type' => $type,
                'settlement_method' => $referenceInvoice->settlement_method,
                'cash_amount' => $referenceInvoice->cash_amount,
                'reference_invoice_id' => $referenceInvoice->id,
                'status' => InvoiceStatus::Draft,
                'subtotal' => $referenceInvoice->subtotal,
                'discount_total' => $referenceInvoice->discount_total,
                'tax_total' => $referenceInvoice->tax_total,
                'total' => $referenceInvoice->total,
            ]);

            foreach ($referenceInvoice->items as $item) {
                $invoice->items()->create($item->only([
                    'good_id',
                    'description',
                    'commodity_code',
                    'quantity',
                    'unit_price',
                    'tax_rate',
                    'discount',
                    'subtotal',
                    'tax_amount',
                    'total',
                ]));
            }

            return $invoice->load(['customer', 'items.good', 'referenceInvoice']);
        });
    }
}
