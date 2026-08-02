<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use App\Queries\Dashboard\DashboardAnalyticsQuery;
use App\Services\Dashboard\DashboardDateRangeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses inclusive API dates and half-open created and completed timestamp boundaries', function (): void {
    foreach (['2026-08-02 00:00:00', '2026-08-02 23:59:59', '2026-08-03 00:00:00'] as $timestamp) {
        Order::factory()->create([
            'status' => OrderStatus::COMPLETED,
            'total' => '10.00',
            'paid_amount' => '10.00',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            'completed_at' => $timestamp,
        ]);
    }

    $range = app(DashboardDateRangeResolver::class)->todayRange(
        CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC'),
    );
    $query = app(DashboardAnalyticsQuery::class);
    $financial = $query->financialSummary($range, $range);

    expect($query->orderCount($range, null))->toBe(2)
        ->and($financial['sales']['today'])->toBe('20.00')
        ->and($financial['collectedSales']['today'])->toBe('20.00');
});
