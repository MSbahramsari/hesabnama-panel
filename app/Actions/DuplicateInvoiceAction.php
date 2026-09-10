<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\User;

class DuplicateInvoiceAction
{
    public function __construct(private SaveInvoiceAction $saveInvoice) {}

    public function handle(User $user, Invoice $source, string $number): Invoice
    {
        $source->loadMissing('items');

        return $this->saveInvoice->handle($user, [
            'customer_id' => $source->customer_id,
            'number' => $number,
            'invoice_date' => now('Asia/Tehran')->format('Y-m-d'),
            'description' => $source->description,
            'settlement_method' => $source->settlement_method->value,
            'cash_amount' => $source->cash_amount,
            'items' => $source->items->map(fn ($item): array => [
                'good_id' => $item->good_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
                'discount' => $item->discount,
            ])->all(),
        ]);
    }
}
