<?php

declare(strict_types=1);

use App\Services\Dashboard\DashboardDateRangeResolver;
use App\Services\Dashboard\DashboardPerformanceProjector;
use Carbon\CarbonImmutable;

it('zero fills six chronological months with localized labels', function (): void {
    app()->setLocale('en');
    $ranges = app(DashboardDateRangeResolver::class)->sixMonthWindow(
        CarbonImmutable::parse('2026-02-15 12:00:00', 'UTC'),
    );

    $points = app(DashboardPerformanceProjector::class)->project([
        (object) ['month' => '2025-11', 'sales' => '125.5', 'orders_count' => 2],
        (object) ['month' => '2026-02', 'sales' => null, 'orders_count' => 1],
    ], $ranges);

    expect($points)->toHaveCount(6)
        ->and(array_column($points, 'month'))->toBe([
            '2025-09', '2025-10', '2025-11', '2025-12', '2026-01', '2026-02',
        ])
        ->and($points[0])->toBe([
            'month' => '2025-09',
            'label' => 'September 2025',
            'sales' => '0.00',
            'ordersCount' => 0,
        ])
        ->and($points[2]['sales'])->toBe('125.50')
        ->and($points[2]['ordersCount'])->toBe(2)
        ->and($points[5]['sales'])->toBe('0.00');
});

it('localizes the same month identity in Arabic', function (): void {
    app()->setLocale('ar');
    $ranges = app(DashboardDateRangeResolver::class)->sixMonthWindow(
        CarbonImmutable::parse('2026-08-15 12:00:00', 'UTC'),
    );

    $points = app(DashboardPerformanceProjector::class)->project([], $ranges);

    expect($points[5]['month'])->toBe('2026-08')
        ->and($points[5]['label'])->toBe(__('dashboard.performance.months.august').' 2026');
});
