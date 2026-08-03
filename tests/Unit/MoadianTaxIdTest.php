<?php

use App\Services\Moadian\MoadianNormalizer;
use App\Services\Moadian\TaxIdGenerator;
use Carbon\CarbonImmutable;

it('generates tax identifiers compatible with the official algorithm', function (
    int $internalInvoiceId,
    string $expected,
) {
    $generated = (new TaxIdGenerator)->generate(
        'DEF5GH',
        CarbonImmutable::parse('2020-07-20 01:00:10', 'UTC'),
        $internalInvoiceId,
    );

    expect($generated)->toBe($expected)->toHaveLength(22);
})->with([
    [12, 'DEF5GH0481F000000000C2'],
    [8173, 'DEF5GH0481F0000001FED8'],
    [2572613409, 'DEF5GH0481F009956F7211'],
]);

it('normalizes nested request data before signing', function () {
    $normalized = (new MoadianNormalizer)->normalize([
        'KD' => 12.94,
        'KB' => 'ABC',
        'KA' => [
            ['B' => 2, 'A' => 1],
            ['A' => 3, 'B' => 4],
        ],
    ]);

    expect($normalized)->toBe('1#2#3#4#ABC#12.94');
});
