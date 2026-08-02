<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('records representative MySQL plans for each dashboard query family', function (): void {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Dashboard EXPLAIN verification requires MySQL.');
    }

    Order::factory()->count(20)->create();

    $plans = [
        'financial' => DB::select('EXPLAIN SELECT SUM(CASE WHEN status != 4 THEN total ELSE 0 END) FROM orders'),
        'collected' => DB::select('EXPLAIN SELECT SUM(paid_amount) FROM orders WHERE status = 3 AND completed_at >= ? AND completed_at < ?', ['2026-08-01 00:00:00', '2026-09-01 00:00:00']),
        'count' => DB::select('EXPLAIN SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < ? AND status = 0', ['2026-08-01 00:00:00', '2026-09-01 00:00:00']),
        'performance' => DB::select("EXPLAIN SELECT DATE_FORMAT(created_at, '%Y-%m'), SUM(total) FROM orders WHERE status != 4 AND created_at >= ? AND created_at < ? GROUP BY DATE_FORMAT(created_at, '%Y-%m')", ['2026-03-01 00:00:00', '2026-09-01 00:00:00']),
    ];

    foreach ($plans as $plan) {
        expect($plan)->not->toBeEmpty()
            ->and(data_get($plan, '0.table'))->toBe('orders')
            ->and((string) data_get($plan, '0.type'))->toBeIn(['ALL', 'index', 'range', 'ref']);
    }
});
