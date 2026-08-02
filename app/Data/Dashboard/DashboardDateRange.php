<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use Carbon\CarbonImmutable;

readonly class DashboardDateRange
{
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public CarbonImmutable $startAt,
        public CarbonImmutable $exclusiveEndAt,
        public string $source,
    ) {}
}
