<?php

declare(strict_types=1);

use App\Data\Dashboard\DashboardFilterData;
use App\Services\Dashboard\DashboardDateRangeResolver;
use Carbon\CarbonImmutable;

it('resolves the approved UTC order and sales periods', function (): void {
    $resolver = app(DashboardDateRangeResolver::class);
    $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');

    $today = $resolver->resolveOrderCountRange(new DashboardFilterData, $now);
    $week = $resolver->resolveOrderCountRange(new DashboardFilterData(ordersPeriod: 'this_week'), $now);
    $month = $resolver->resolveOrderCountRange(new DashboardFilterData(ordersPeriod: 'this_month'), $now);
    $sales = $resolver->resolveSalesPeriodRange(new DashboardFilterData, $now);

    expect($today->dateFrom)->toBe('2026-08-02')
        ->and($today->dateTo)->toBe('2026-08-02')
        ->and($today->exclusiveEndAt->toIso8601String())->toBe('2026-08-03T00:00:00+00:00')
        ->and($week->dateFrom)->toBe('2026-08-01')
        ->and($week->dateTo)->toBe('2026-08-07')
        ->and($month->dateFrom)->toBe('2026-08-01')
        ->and($month->dateTo)->toBe('2026-08-31')
        ->and($sales->dateFrom)->toBe('2026-08-01')
        ->and($sales->dateTo)->toBe('2026-08-31');
});

it('resolves independent custom sales and order ranges', function (): void {
    $resolver = app(DashboardDateRangeResolver::class);
    $filters = new DashboardFilterData(
        ordersPeriod: 'this_week',
        dateFrom: '2024-02-29',
        dateTo: '2024-03-01',
    );
    $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');

    $orders = $resolver->resolveOrderCountRange($filters, $now);
    $sales = $resolver->resolveSalesPeriodRange($filters, $now);

    expect($orders->dateFrom)->toBe('2026-08-01')
        ->and($orders->dateTo)->toBe('2026-08-07')
        ->and($sales->dateFrom)->toBe('2024-02-29')
        ->and($sales->dateTo)->toBe('2024-03-01');
});

it('accepts exactly 366 inclusive custom days and rejects 367', function (): void {
    $resolver = app(DashboardDateRangeResolver::class);

    $range = $resolver->customRange('2024-01-01', '2024-12-31');

    expect($range->dateFrom)->toBe('2024-01-01')
        ->and($range->dateTo)->toBe('2024-12-31');

    expect(fn () => $resolver->customRange('2023-01-01', '2024-01-02'))
        ->toThrow(InvalidArgumentException::class);
});
