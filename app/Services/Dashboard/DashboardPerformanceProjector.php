<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\DashboardDateRange;

class DashboardPerformanceProjector
{
    public function __construct(
        private readonly DashboardMoneyResultNormalizer $moneyNormalizer,
    ) {}

    /**
     * @param  iterable<object|array<string, mixed>>  $rows
     * @param  array<int, DashboardDateRange>  $monthRanges
     * @return array<int, array{month: string, label: string, sales: string, ordersCount: int}>
     */
    public function project(iterable $rows, array $monthRanges): array
    {
        $rowsByMonth = collect($rows)->keyBy(fn (object|array $row): string => (string) data_get($row, 'month'));

        return array_map(function (DashboardDateRange $range) use ($rowsByMonth): array {
            $month = $range->startAt->format('Y-m');
            $row = $rowsByMonth->get($month);
            $monthName = strtolower($range->startAt->format('F'));

            return [
                'month' => $month,
                'label' => __('dashboard.performance.months.'.$monthName).' '.$range->startAt->format('Y'),
                'sales' => $this->moneyNormalizer->format(data_get($row, 'sales')),
                'ordersCount' => (int) data_get($row, 'orders_count', 0),
            ];
        }, $monthRanges);
    }
}
