<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Data\Dashboard\DashboardDateRange;
use App\Data\Dashboard\DashboardFilterData;
use Carbon\CarbonImmutable;

class DashboardDateRangeResolver
{
    public function resolveOrderCountRange(
        DashboardFilterData $filters,
        ?CarbonImmutable $now = null,
    ): DashboardDateRange {
        $now ??= CarbonImmutable::now('UTC');

        return match ($filters->ordersPeriod) {
            'this_week' => $this->thisWeekRange($now),
            'this_month' => $this->thisMonthRange($now),
            'custom' => $this->customRange(
                $filters->dateFrom ?? $now->toDateString(),
                $filters->dateTo ?? $now->toDateString(),
            ),
            default => $this->todayRange($now),
        };
    }

    public function resolveSalesPeriodRange(
        DashboardFilterData $filters,
        ?CarbonImmutable $now = null,
    ): DashboardDateRange {
        $now ??= CarbonImmutable::now('UTC');

        if ($filters->usesCustomOrdersPeriod() && $filters->hasDatePair()) {
            return $this->customRange((string) $filters->dateFrom, (string) $filters->dateTo);
        }

        if ($filters->hasDatePair()) {
            return $this->customRange((string) $filters->dateFrom, (string) $filters->dateTo);
        }

        return $this->thisMonthRange($now);
    }

    public function todayRange(?CarbonImmutable $now = null): DashboardDateRange
    {
        $now ??= CarbonImmutable::now('UTC');
        $startAt = $now->startOfDay();

        return $this->rangeFromBounds($startAt, $startAt->addDay(), 'today');
    }

    public function thisWeekRange(?CarbonImmutable $now = null): DashboardDateRange
    {
        $now ??= CarbonImmutable::now('UTC');

        $startAt = $now->startOfDay();

        while ((int) $startAt->dayOfWeekIso !== 6) {
            $startAt = $startAt->subDay();
        }

        return $this->rangeFromBounds($startAt, $startAt->addDays(7), 'this_week');
    }

    public function thisMonthRange(?CarbonImmutable $now = null): DashboardDateRange
    {
        $now ??= CarbonImmutable::now('UTC');
        $startAt = $now->startOfMonth()->startOfDay();

        return $this->rangeFromBounds($startAt, $startAt->addMonth(), 'this_month');
    }

    public function customRange(string $dateFrom, string $dateTo): DashboardDateRange
    {
        $startAt = CarbonImmutable::createFromFormat('!Y-m-d', $dateFrom, 'UTC');
        $endAt = CarbonImmutable::createFromFormat('!Y-m-d', $dateTo, 'UTC');

        if (
            $startAt === false
            || $endAt === false
            || $startAt->format('Y-m-d') !== $dateFrom
            || $endAt->format('Y-m-d') !== $dateTo
            || $startAt->greaterThan($endAt)
            || $startAt->diffInDays($endAt) + 1 > 366
        ) {
            throw new \InvalidArgumentException('Invalid dashboard custom date range.');
        }

        $exclusiveEndAt = $endAt->addDay();

        return $this->rangeFromBounds($startAt, $exclusiveEndAt, 'custom');
    }

    /**
     * @return array<int, DashboardDateRange>
     */
    public function sixMonthWindow(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $month = $now->startOfMonth()->startOfDay()->subMonths(5);
        $ranges = [];

        for ($index = 0; $index < 6; $index++) {
            $startAt = $month->addMonths($index);
            $ranges[] = $this->rangeFromBounds($startAt, $startAt->addMonth(), 'month');
        }

        return $ranges;
    }

    private function rangeFromBounds(
        CarbonImmutable $startAt,
        CarbonImmutable $exclusiveEndAt,
        string $source,
    ): DashboardDateRange {
        return new DashboardDateRange(
            dateFrom: $startAt->toDateString(),
            dateTo: $exclusiveEndAt->subDay()->toDateString(),
            startAt: $startAt,
            exclusiveEndAt: $exclusiveEndAt,
            source: $source,
        );
    }
}
