<?php

namespace App\Services\Spreadsheet;

class BankStatementConversion
{
    /**
     * @param  array<int, array{date: string, description: string, debit: float, credit: float, tracking_number: string}>  $transactions
     */
    public function __construct(
        public string $detectedBank,
        public array $transactions,
        public int $sourceRows,
        public int $skippedRows,
    ) {}

    public function debitTotal(): float
    {
        return array_sum(array_column($this->transactions, 'debit'));
    }

    public function creditTotal(): float
    {
        return array_sum(array_column($this->transactions, 'credit'));
    }
}
