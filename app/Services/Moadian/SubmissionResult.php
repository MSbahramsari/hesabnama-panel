<?php

namespace App\Services\Moadian;

readonly class SubmissionResult
{
    public function __construct(
        public string $uid,
        public string $referenceNumber,
        public ?string $taxId = null,
    ) {}
}
