<?php

declare(strict_types=1);

use App\Data\Dashboard\DashboardFilterData;
use App\Models\Order;
use App\Queries\Dashboard\DashboardAnalyticsQuery;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps dashboard Order reads at three SELECT statements independent of row volume', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00', 'UTC'));
    $selects = [];

    DB::listen(function (QueryExecuted $query) use (&$selects): void {
        $sql = strtolower($query->sql);

        if (str_starts_with(ltrim($sql), 'select') && str_contains($sql, 'orders')) {
            $selects[] = $query->sql;
        }
    });

    app(DashboardAnalyticsQuery::class)->execute(new DashboardFilterData);
    expect($selects)->toHaveCount(3);

    Order::factory()->count(40)->create();
    $selects = [];

    app(DashboardAnalyticsQuery::class)->execute(new DashboardFilterData(status: 0));

    expect($selects)->toHaveCount(3);
    Carbon::setTestNow();
});
