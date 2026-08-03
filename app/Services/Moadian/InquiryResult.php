<?php

namespace App\Services\Moadian;

readonly class InquiryResult
{
    public function __construct(
        public string $status,
        public ?string $taxResult = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'SUCCESS' && $this->taxResult === 'SUCCESS';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }
}
