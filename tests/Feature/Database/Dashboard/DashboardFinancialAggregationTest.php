<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use App\Queries\Dashboard\DashboardAnalyticsQuery;
use App\Services\Dashboard\DashboardDateRangeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates null-safe fixed-precision financial aggregates with cancelled exclusion and zero floor', function (): void {
    $rows = [
        [OrderStatus::PENDING, '100.00', '20.00', '2026-08-02 00:00:00', null],
        [OrderStatus::CONFIRMED, '75.25', '0.00', '2026-08-02 23:59:59', null],
        [OrderStatus::IN_PROGRESS, '50.00', '70.00', '2026-07-01 10:00:00', null],
        [OrderStatus::COMPLETED, '200.00', '150.00', '2026-08-01 10:00:00', '2026-08-02 12:00:00'],
        [OrderStatus::CANCELLED, '999.00', '999.00', '2026-08-02 10:00:00', '2026-08-02 10:00:00'],
    ];

    foreach ($rows as [$status, $total, $paid, $createdAt, $completedAt]) {
        Order::factory()->create([
            'status' => $status,
            'total' => $total,
            'paid_amount' => $paid,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'completed_at' => $completedAt,
        ]);
    }

    $resolver = app(DashboardDateRangeResolver::class);
    $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');
    $result = app(DashboardAnalyticsQuery::class)->financialSummary(
        $resolver->todayRange($now),
        $resolver->thisMonthRange($now),
    );

    expect($result)->toBe([
        'sales' => ['total' => '425.25', 'today' => '175.25', 'period' => '375.25'],
        'collectedSales' => ['total' => '150.00', 'today' => '150.00', 'period' => '150.00'],
        'uncollectedSales' => ['total' => '205.25', 'today' => '155.25'],
    ]);
});

it('returns exact zero money strings for an empty database', function (): void {
    $resolver = app(DashboardDateRangeResolver::class);
    $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');

    expect(app(DashboardAnalyticsQuery::class)->financialSummary(
        $resolver->todayRange($now),
        $resolver->thisMonthRange($now),
    ))->toBe([
        'sales' => ['total' => '0.00', 'today' => '0.00', 'period' => '0.00'],
        'collectedSales' => ['total' => '0.00', 'today' => '0.00', 'period' => '0.00'],
        'uncollectedSales' => ['total' => '0.00', 'today' => '0.00'],
    ]);
});
