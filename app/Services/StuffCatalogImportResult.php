<?php

namespace App\Services;

class StuffCatalogImportResult
{
    public function __construct(
        public readonly int $newRows,
        public readonly int $updatedRows,
        public readonly int $unchangedRows,
        public readonly int $skippedRows,
    ) {}

    public function validRows(): int
    {
        return $this->newRows + $this->updatedRows + $this->unchangedRows;
    }

    public function processedRows(): int
    {
        return $this->validRows() + $this->skippedRows;
    }
}
