<?php

namespace App\Services\Moadian;

readonly class InquiryResult
{
    public function __construct(
        public string $status,
        public ?string $taxResult = null,
        public ?string $confirmationReferenceId = null,
        public ?string $packetType = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === 'SUCCESS' && mb_strtoupper((string) $this->taxResult) === 'SUCCESS';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED'
            || ($this->taxResult !== null && mb_strtoupper($this->taxResult) !== 'SUCCESS');
    }
}
