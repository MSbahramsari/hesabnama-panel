<?php

namespace App\Actions;

use App\Enums\ElectronicInvoiceType;
use App\Enums\InvoiceStatus;
use App\Enums\SettlementMethod;
use App\Models\Good;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Moadian\RialInvoiceCalculator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveInvoiceAction
{
    public function __construct(private RialInvoiceCalculator $calculator) {}

    /** @param array{customer_id: int, number: string, invoice_date: string, description?: string|null, settlement_method: string, cash_amount?: numeric-string|int|float|null, items: array<int, array{good_id: int, quantity: numeric-string|int|float, unit_price: numeric-string|int|float, tax_rate: numeric-string|int|float, discount?: numeric-string|int|float}>} $data */
    public function handle(User $user, array $data, ?Invoice $invoice = null): Invoice
    {
        return DB::transaction(function () use ($user, $data, $invoice): Invoice {
            $invoice ??= new Invoice(['user_id' => $user->id]);
            $wasRejectedByMoadian = $invoice->exists && $invoice->status === InvoiceStatus::MoadianError;
            $electronicTypeValue = $data['electronic_invoice_type'] ?? $invoice->electronic_invoice_type ?? ElectronicInvoiceType::Full;
            $electronicType = $electronicTypeValue instanceof ElectronicInvoiceType
                ? $electronicTypeValue
                : ElectronicInvoiceType::from((int) $electronicTypeValue);
            $data['electronic_invoice_type'] = $electronicType->value;
            $data['settlement_method'] ??= $invoice->settlement_method?->value ?? SettlementMethod::Cash->value;

            if ($electronicType !== ElectronicInvoiceType::Full) {
                $data['settlement_method'] = SettlementMethod::Cash->value;
            }

            if ($electronicType === ElectronicInvoiceType::PosReceipt) {
                $data['customer_id'] = null;
            } else {
                foreach (['payment_switch_number', 'payment_acceptor_number', 'payment_terminal_number', 'payment_method', 'payment_tracking_number', 'payer_card_number', 'payer_identifier', 'payment_at'] as $paymentField) {
                    $data[$paymentField] = null;
                }
            }
            $invoice->fill(Arr::only($data, [
                'customer_id', 'number', 'invoice_date', 'description', 'electronic_invoice_type', 'settlement_method',
                'payment_switch_number', 'payment_acceptor_number', 'payment_terminal_number', 'payment_method',
                'payment_tracking_number', 'payer_card_number', 'payer_identifier', 'payment_at',
            ]));
            $invoice->status = InvoiceStatus::Draft;

            if ($wasRejectedByMoadian) {
                $invoice->fill([
                    'moadian_status' => null,
                    'moadian_tax_result' => null,
                    'moadian_confirmation_reference_id' => null,
                    'moadian_packet_type' => null,
                    'submission_uid' => null,
                    'moadian_serial' => null,
                    'tax_id' => null,
                    'reference_number' => null,
                    'sent_at' => null,
                    'last_inquired_at' => null,
                    'confirmed_at' => null,
                    'error_message' => null,
                ]);
            }

            $invoice->save();

            $invoice->items()->delete();
            $subtotal = 0;
            $discountTotal = 0;
            $taxTotal = 0;

            $goods = Good::query()
                ->whereBelongsTo($user)
                ->whereIn('id', collect($data['items'])->pluck('good_id'))
                ->get()
                ->keyBy('id');

            foreach ($data['items'] as $itemData) {
                $good = $goods->get((int) $itemData['good_id']);
                abort_unless($good, 422, 'یکی از کالاها معتبر نیست.');

                $quantity = bcadd((string) $itemData['quantity'], '0', 8);
                $unitPrice = bcadd((string) $itemData['unit_price'], '0', 8);
                $taxRate = bcadd((string) $itemData['tax_rate'], '0', 2);
                if (bccomp(bcmul($quantity, $unitPrice, 0), '9999999999999999', 0) > 0) {
                    throw ValidationException::withMessages(['items' => 'مبلغ ردیف از محدوده قابل ذخیره سامانه بیشتر است.']);
                }
                $amounts = $this->calculator->line($quantity, $unitPrice, $taxRate, $itemData['discount'] ?? 0);
                $lineSubtotal = $amounts['subtotal'];
                $discount = $amounts['discount'];
                $taxAmount = $amounts['tax_amount'];
                $lineTotal = $amounts['total'];

                if ($lineSubtotal <= 0 || $subtotal + $lineSubtotal > 9999999999999999
                    || $subtotal - $discountTotal + $taxTotal + $lineTotal > 9999999999999999) {
                    throw ValidationException::withMessages(['items' => 'مبلغ ردیف پس از قطع اعشار باید حداقل یک ریال و مجموع صورتحساب در محدوده مجاز سامانه باشد.']);
                }

                $invoice->items()->create([
                    'good_id' => $good->id,
                    'description' => filled($itemData['description'] ?? null) ? trim($itemData['description']) : $good->name,
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

            $netAmount = $subtotal - $discountTotal;
            $settlementMethod = SettlementMethod::from($data['settlement_method']);
            $cashAmount = match ($settlementMethod) {
                SettlementMethod::Cash => $netAmount,
                SettlementMethod::Credit => 0,
                SettlementMethod::Mixed => $this->calculator->rial($data['cash_amount'] ?? 0),
            };

            if ($settlementMethod === SettlementMethod::Mixed && ($cashAmount <= 0 || $cashAmount >= $netAmount)) {
                throw ValidationException::withMessages([
                    'cash_amount' => 'مبلغ نقدی در روش ترکیبی باید بزرگ‌تر از صفر و کم‌تر از مبلغ قبل از مالیات باشد.',
                ]);
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'total' => $netAmount + $taxTotal,
                'cash_amount' => $cashAmount,
            ]);

            return $invoice->load(['customer', 'items.good']);
        });
    }
}
