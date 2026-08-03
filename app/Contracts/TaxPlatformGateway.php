<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Services\Moadian\InquiryResult;
use App\Services\Moadian\SubmissionResult;

interface TaxPlatformGateway
{
    /** @return array{name: string, national_id: string, type: string, address: string, postal_code: string}|null */
    public function lookupCustomer(string $economicCode): ?array;

    /** @return array{name: string, unit: string, unit_price: int, tax_rate: int}|null */
    public function lookupGood(string $commodityCode): ?array;

    public function submit(Invoice $invoice): SubmissionResult;

    public function inquire(Invoice $invoice): InquiryResult;

    public function isDemo(): bool;
}
