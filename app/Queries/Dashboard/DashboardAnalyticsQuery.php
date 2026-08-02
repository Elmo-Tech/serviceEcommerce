<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Data\Dashboard\DashboardDateRange;
use App\Data\Dashboard\DashboardFilterData;
use App\Enums\Orders\OrderStatus;
use App\Queries\Dashboard\Filters\CompletedAtRangeQueryFilter;
use App\Queries\Dashboard\Filters\CreatedAtRangeQueryFilter;
use App\Queries\Dashboard\Filters\CurrentStatusQueryFilter;
use App\Queries\Dashboard\Filters\NonCancelledQueryFilter;
use App\Services\Dashboard\DashboardDateRangeResolver;
use App\Services\Dashboard\DashboardMoneyResultNormalizer;
use App\Services\Dashboard\DashboardPerformanceProjector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsQuery
{
    public function __construct(
        private readonly NonCancelledQueryFilter $nonCancelledQueryFilter,
        private readonly CreatedAtRangeQueryFilter $createdAtRangeQueryFilter,
        private readonly CompletedAtRangeQueryFilter $completedAtRangeQueryFilter,
        private readonly CurrentStatusQueryFilter $currentStatusQueryFilter,
        private readonly DashboardMoneyResultNormalizer $dashboardMoneyResultNormalizer,
        private readonly DashboardDateRangeResolver $dashboardDateRangeResolver,
        private readonly DashboardPerformanceProjector $dashboardPerformanceProjector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(DashboardFilterData $filters): array
    {
        $now = CarbonImmutable::now('UTC');
        $todayRange = $this->dashboardDateRangeResolver->todayRange($now);
        $salesPeriodRange = $this->dashboardDateRangeResolver->resolveSalesPeriodRange($filters, $now);
        $ordersRange = $this->dashboardDateRangeResolver->resolveOrderCountRange($filters, $now);
        $monthRanges = $this->dashboardDateRangeResolver->sixMonthWindow($now);
        $financials = $this->financialSummary($todayRange, $salesPeriodRange);

        return [
            'salesPeriod' => [
                'dateFrom' => $salesPeriodRange->dateFrom,
                'dateTo' => $salesPeriodRange->dateTo,
            ],
            ...$financials,
            'orders' => [
                'period' => $filters->ordersPeriod,
                'dateFrom' => $ordersRange->dateFrom,
                'dateTo' => $ordersRange->dateTo,
                'status' => $filters->status,
                'count' => $this->orderCount($ordersRange, $filters->status),
            ],
            'performance' => $this->dashboardPerformanceProjector->project(
                $this->performanceRows($monthRanges),
                $monthRanges,
            ),
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function financialSummary(
        DashboardDateRange $todayRange,
        DashboardDateRange $salesPeriodRange,
    ): array {
        $row = DB::table('orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN status != ? THEN COALESCE(total, 0) ELSE 0 END), 0) as sales_total', [OrderStatus::CANCELLED->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status != ? AND created_at >= ? AND created_at < ? THEN COALESCE(total, 0) ELSE 0 END), 0) as sales_today',
                [OrderStatus::CANCELLED->value, $todayRange->startAt, $todayRange->exclusiveEndAt],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status != ? AND created_at >= ? AND created_at < ? THEN COALESCE(total, 0) ELSE 0 END), 0) as sales_period',
                [OrderStatus::CANCELLED->value, $salesPeriodRange->startAt, $salesPeriodRange->exclusiveEndAt],
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN COALESCE(paid_amount, 0) ELSE 0 END), 0) as collected_total', [OrderStatus::COMPLETED->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? AND completed_at IS NOT NULL AND completed_at >= ? AND completed_at < ? THEN COALESCE(paid_amount, 0) ELSE 0 END), 0) as collected_today',
                [OrderStatus::COMPLETED->value, $todayRange->startAt, $todayRange->exclusiveEndAt],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? AND completed_at IS NOT NULL AND completed_at >= ? AND completed_at < ? THEN COALESCE(paid_amount, 0) ELSE 0 END), 0) as collected_period',
                [OrderStatus::COMPLETED->value, $salesPeriodRange->startAt, $salesPeriodRange->exclusiveEndAt],
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN status != ? THEN GREATEST(COALESCE(total, 0) - COALESCE(paid_amount, 0), 0) ELSE 0 END), 0) as uncollected_total', [OrderStatus::CANCELLED->value])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status != ? AND created_at >= ? AND created_at < ? THEN GREATEST(COALESCE(total, 0) - COALESCE(paid_amount, 0), 0) ELSE 0 END), 0) as uncollected_today',
                [OrderStatus::CANCELLED->value, $todayRange->startAt, $todayRange->exclusiveEndAt],
            )
            ->first();

        return [
            'sales' => $this->dashboardMoneyResultNormalizer->financialBlock([
                'total' => data_get($row, 'sales_total'),
                'today' => data_get($row, 'sales_today'),
                'period' => data_get($row, 'sales_period'),
            ]),
            'collectedSales' => $this->dashboardMoneyResultNormalizer->financialBlock([
                'total' => data_get($row, 'collected_total'),
                'today' => data_get($row, 'collected_today'),
                'period' => data_get($row, 'collected_period'),
            ]),
            'uncollectedSales' => $this->dashboardMoneyResultNormalizer->uncollectedBlock([
                'total' => data_get($row, 'uncollected_total'),
                'today' => data_get($row, 'uncollected_today'),
            ]),
        ];
    }

    public function orderCount(DashboardDateRange $range, ?int $status): int
    {
        $query = DB::table('orders');
        ($this->createdAtRangeQueryFilter)($query, $range);
        ($this->currentStatusQueryFilter)($query, $status);

        return $query->count();
    }

    /**
     * @param  array<int, DashboardDateRange>  $monthRanges
     * @return iterable<object>
     */
    public function performanceRows(array $monthRanges): iterable
    {
        if ($monthRanges === []) {
            return [];
        }

        $window = new DashboardDateRange(
            dateFrom: $monthRanges[0]->dateFrom,
            dateTo: $monthRanges[array_key_last($monthRanges)]->dateTo,
            startAt: $monthRanges[0]->startAt,
            exclusiveEndAt: $monthRanges[array_key_last($monthRanges)]->exclusiveEndAt,
            source: 'six_months',
        );

        $query = DB::table('orders');
        ($this->nonCancelledQueryFilter)($query);
        ($this->createdAtRangeQueryFilter)($query, $window);

        return $query
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->selectRaw('COALESCE(SUM(COALESCE(total, 0)), 0) as sales')
            ->selectRaw('COUNT(*) as orders_count')
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month')
            ->get();
    }
}
