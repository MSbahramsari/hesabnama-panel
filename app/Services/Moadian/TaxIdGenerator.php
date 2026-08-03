<?php

namespace App\Services\Moadian;

use App\Exceptions\MoadianConfigurationException;
use DateTimeInterface;

class TaxIdGenerator
{
    private const MULTIPLICATION_TABLE = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
        [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
        [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
        [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
        [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
        [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
        [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
        [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
    ];

    private const PERMUTATION_TABLE = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
        [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
        [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
        [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
        [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
        [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
    ];

    private const INVERSE_TABLE = [0, 4, 3, 2, 1, 5, 6, 7, 8, 9];

    public function generate(string $fiscalId, DateTimeInterface $issuedAt, int $internalInvoiceId): string
    {
        $fiscalId = mb_strtoupper($fiscalId);

        if (! preg_match('/^[A-Z0-9]{6}$/', $fiscalId)) {
            throw new MoadianConfigurationException('شناسه یکتای حافظه مالیاتی برای ساخت شماره مالیاتی معتبر نیست.');
        }

        if ($internalInvoiceId < 1) {
            throw new MoadianConfigurationException('شناسه داخلی صورتحساب برای ساخت شماره مالیاتی معتبر نیست.');
        }

        $daysSinceEpoch = (int) floor($issuedAt->getTimestamp() / 86400);
        $decimalFiscalId = '';

        foreach (str_split($fiscalId) as $character) {
            $decimalFiscalId .= ctype_digit($character) ? $character : (string) ord($character);
        }

        $checksumInput = $decimalFiscalId
            .str_pad((string) $daysSinceEpoch, 6, '0', STR_PAD_LEFT)
            .str_pad((string) $internalInvoiceId, 12, '0', STR_PAD_LEFT);

        $taxId = $fiscalId
            .str_pad(dechex($daysSinceEpoch), 5, '0', STR_PAD_LEFT)
            .str_pad(dechex($internalInvoiceId), 10, '0', STR_PAD_LEFT)
            .$this->verhoeffChecksum($checksumInput);

        return mb_strtoupper($taxId);
    }

    private function verhoeffChecksum(string $number): int
    {
        $checksum = 0;
        $length = strlen($number);

        for ($index = 0; $index < $length; $index++) {
            $digit = (int) $number[$length - $index - 1];
            $checksum = self::MULTIPLICATION_TABLE[$checksum][self::PERMUTATION_TABLE[($index + 1) % 8][$digit]];
        }

        return self::INVERSE_TABLE[$checksum];
    }
}
