<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveInvoiceAction
{
    /** @param array{customer_id: int, number: string, invoice_date: string, description?: string|null, items: array<int, array{good_id: int, quantity: numeric-string|int|float, unit_price: numeric-string|int|float, tax_rate: numeric-string|int|float, discount?: numeric-string|int|float}>} $data */
    public function handle(User $user, array $data, ?Invoice $invoice = null): Invoice
    {
        return DB::transaction(function () use ($user, $data, $invoice): Invoice {
            $invoice ??= new Invoice(['user_id' => $user->id]);
            $invoice->fill(Arr::only($data, ['customer_id', 'number', 'invoice_date', 'description']));
            $invoice->status = InvoiceStatus::Draft;
            $invoice->save();

            $invoice->items()->delete();
            $subtotal = 0.0;
            $discountTotal = 0.0;
            $taxTotal = 0.0;

            $goods = Good::query()
                ->whereBelongsTo($user)
                ->whereIn('id', collect($data['items'])->pluck('good_id'))
                ->get()
                ->keyBy('id');

            foreach ($data['items'] as $itemData) {
                $good = $goods->get((int) $itemData['good_id']);
                abort_unless($good, 422, 'یکی از کالاها معتبر نیست.');

                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $taxRate = (float) $itemData['tax_rate'];
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $discount = min(round((float) ($itemData['discount'] ?? 0), 2), $lineSubtotal);
                $taxAmount = round(($lineSubtotal - $discount) * $taxRate / 100, 2);
                $lineTotal = $lineSubtotal - $discount + $taxAmount;

                $invoice->items()->create([
                    'good_id' => $good->id,
                    'description' => $good->name,
                    'commodity_code' => $good->commodity_code,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'discount' => $discount,
                    'subtotal' => $lineSubtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineSubtotal;
                $discountTotal += $discount;
                $taxTotal += $taxAmount;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'total' => $subtotal - $discountTotal + $taxTotal,
            ]);

            return $invoice->load(['customer', 'items.good']);
        });
    }
}
