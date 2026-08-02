<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use App\Queries\Dashboard\DashboardAnalyticsQuery;
use App\Services\Dashboard\DashboardDateRangeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts all statuses or one exact status inside the effective range', function (): void {
    foreach (OrderStatus::cases() as $status) {
        Order::factory()->create([
            'status' => $status,
            'created_at' => '2026-08-02 12:00:00',
            'updated_at' => '2026-08-02 12:00:00',
        ]);
    }
    Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'created_at' => '2026-08-03 00:00:00',
        'updated_at' => '2026-08-03 00:00:00',
    ]);

    $range = app(DashboardDateRangeResolver::class)->todayRange(
        CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC'),
    );
    $query = app(DashboardAnalyticsQuery::class);

    expect($query->orderCount($range, null))->toBe(5);

    foreach (OrderStatus::cases() as $status) {
        expect($query->orderCount($range, $status->value))->toBe(1);
    }
});
