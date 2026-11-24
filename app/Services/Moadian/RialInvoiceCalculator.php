<?php

namespace App\Services\Moadian;

class RialInvoiceCalculator
{
    /** @return array{subtotal: int, discount: int, tax_amount: int, total: int} */
    public function line(string|int|float $quantity, string|int|float $unitPrice, string|int|float $taxRate, string|int|float $discount = 0): array
    {
        $subtotal = (int) bcmul((string) $quantity, (string) $unitPrice, 0);
        $discount = min($this->rial($discount), $subtotal);
        $netAmount = $subtotal - $discount;
        $tax = (int) bcdiv(bcmul((string) $netAmount, (string) $taxRate, 2), '100', 0);

        return ['subtotal' => $subtotal, 'discount' => $discount, 'tax_amount' => $tax, 'total' => $netAmount + $tax];
    }

    public function rial(string|int|float|null $value): int
    {
        return (int) bcadd((string) ($value ?? 0), '0', 0);
    }

    public function paymentShare(int $amount, int $cashAmount, int $netAmount): int
    {
        return $netAmount > 0
            ? (int) bcdiv(bcmul((string) $amount, (string) $cashAmount, 0), (string) $netAmount, 0)
            : 0;
    }
}
