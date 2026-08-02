<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use App\Queries\Dashboard\DashboardAnalyticsQuery;
use App\Services\Dashboard\DashboardDateRangeResolver;
use App\Services\Dashboard\DashboardPerformanceProjector;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('groups six UTC calendar months across a year boundary and zero fills missing months', function (): void {
    $rows = [
        [OrderStatus::PENDING, '25.00', '2025-11-10 12:00:00'],
        [OrderStatus::COMPLETED, '75.00', '2026-02-01 00:00:00'],
        [OrderStatus::CANCELLED, '999.00', '2026-02-02 00:00:00'],
        [OrderStatus::PENDING, '500.00', '2025-08-31 23:59:59'],
    ];

    foreach ($rows as [$status, $total, $createdAt]) {
        Order::factory()->create([
            'status' => $status,
            'total' => $total,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    $ranges = app(DashboardDateRangeResolver::class)->sixMonthWindow(
        CarbonImmutable::parse('2026-02-15 12:00:00', 'UTC'),
    );
    $query = app(DashboardAnalyticsQuery::class);
    $points = app(DashboardPerformanceProjector::class)->project(
        $query->performanceRows($ranges),
        $ranges,
    );

    expect($points)->toHaveCount(6)
        ->and(array_column($points, 'month'))->toBe([
            '2025-09', '2025-10', '2025-11', '2025-12', '2026-01', '2026-02',
        ])
        ->and($points[2]['sales'])->toBe('25.00')
        ->and($points[2]['ordersCount'])->toBe(1)
        ->and($points[5]['sales'])->toBe('75.00')
        ->and($points[5]['ordersCount'])->toBe(1)
        ->and($points[0]['sales'])->toBe('0.00');
});
