<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Models\Order;
use Database\Seeders\DashboardPermissionsSeeder;
use Database\Seeders\OrdersPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);
    $this->seed(DashboardPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
});

it('sets completed_at on the first completion transition and preserves it on later cancellation', function () {
    $accessToken = (string) loginAdminForTests()->json('data.accessToken');

    Carbon::setTestNow(Carbon::parse('2026-08-02 09:00:00', 'UTC'));

    $order = Order::factory()->create([
        'status' => OrderStatus::IN_PROGRESS,
        'payment_status' => PaymentStatus::PARTIALLY_PAID,
        'completed_at' => null,
    ]);

    $this->patchJson(
        '/api/v1/admin/orders/'.$order->getKey().'/status',
        ['status' => OrderStatus::COMPLETED->value],
        [
            'Authorization' => 'Bearer '.$accessToken,
            'Accept-Language' => 'en',
        ],
    )->assertOk();

    $order->refresh();

    expect($order->completed_at?->toJSON())->toBe('2026-08-02T09:00:00.000000Z');

    Carbon::setTestNow(Carbon::parse('2026-08-02 10:00:00', 'UTC'));

    $this->patchJson(
        '/api/v1/admin/orders/'.$order->getKey().'/status',
        [
            'status' => OrderStatus::CANCELLED->value,
            'reason' => 'Customer requested cancellation',
        ],
        [
            'Authorization' => 'Bearer '.$accessToken,
            'Accept-Language' => 'en',
        ],
    )->assertOk();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::CANCELLED)
        ->and($order->completed_at?->toJSON())->toBe('2026-08-02T09:00:00.000000Z')
        ->and($order->cancelled_at?->toJSON())->toBe('2026-08-02T10:00:00.000000Z');

    Carbon::setTestNow();
});
