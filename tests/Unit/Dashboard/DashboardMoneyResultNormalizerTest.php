<?php

declare(strict_types=1);

use App\Services\Dashboard\DashboardMoneyResultNormalizer;

it('formats money aggregates as exact two-decimal strings', function () {
    $normalizer = app(DashboardMoneyResultNormalizer::class);

    expect($normalizer->format(null))->toBe('0.00')
        ->and($normalizer->format('12'))->toBe('12.00')
        ->and($normalizer->format('12.5'))->toBe('12.50')
        ->and($normalizer->financialBlock([
            'total' => '100',
            'today' => null,
            'period' => '55.2',
        ]))->toBe([
            'total' => '100.00',
            'today' => '0.00',
            'period' => '55.20',
        ])
        ->and($normalizer->uncollectedBlock([
            'total' => '10',
            'today' => '2.345',
        ]))->toBe([
            'total' => '10.00',
            'today' => '2.35',
        ]);
});
