<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('uses matching timestamp precision and an idempotent legacy completion backfill', function (): void {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Feature 007 schema verification requires MySQL.');
    }

    $database = (string) DB::getDatabaseName();
    $columns = DB::table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', $database)
        ->where('TABLE_NAME', 'orders')
        ->whereIn('COLUMN_NAME', ['created_at', 'updated_at', 'completed_at'])
        ->pluck('DATETIME_PRECISION', 'COLUMN_NAME');

    expect($columns)->toHaveCount(3)
        ->and($columns['completed_at'])->toBe($columns['created_at'])
        ->and($columns['completed_at'])->toBe($columns['updated_at']);

    $legacyUpdatedAt = '2026-07-31 23:59:58';
    $legacy = Order::factory()->create([
        'status' => OrderStatus::COMPLETED,
        'completed_at' => null,
        'created_at' => '2026-07-01 00:00:00',
        'updated_at' => $legacyUpdatedAt,
    ]);
    Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'completed_at' => null,
    ]);

    $backfill = static fn (): int => DB::table('orders')
        ->where('status', OrderStatus::COMPLETED->value)
        ->whereNull('completed_at')
        ->update(['completed_at' => DB::raw('updated_at')]);

    expect($backfill())->toBe(1);

    $raw = DB::table('orders')->where('id', $legacy->getKey())->first();

    expect($raw->completed_at)->toBe($legacyUpdatedAt)
        ->and($raw->updated_at)->toBe($legacyUpdatedAt)
        ->and($backfill())->toBe(0)
        ->and(DB::table('orders')
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereNull('completed_at')
            ->count())->toBe(0);
});
