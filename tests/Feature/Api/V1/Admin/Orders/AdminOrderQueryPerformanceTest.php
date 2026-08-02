<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServicePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\OrdersPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);
});

function actingAdminWithOrderReadPermissions(): User
{
    $admin = User::factory()->administrator()->create([
        'email' => 'orders-performance@example.test',
    ]);

    $admin->givePermissionTo(
        'orders.view',
        'order-items.view',
    );

    Sanctum::actingAs($admin);

    return $admin;
}

function trackedQueryCount(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $request();

    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    return count(array_filter($queries, static function (array $query): bool {
        $sql = strtolower((string) ($query['query'] ?? ''));

        return str_starts_with($sql, 'select');
    }));
}

it('keeps admin order index reads within a bounded query count', function () {
    actingAdminWithOrderReadPermissions();

    Order::factory()->count(3)->create()->each(function (Order $order): void {
        OrderItem::factory()->count(2)->create([
            'order_id' => $order->getKey(),
            'price_type' => ServicePriceType::FIXED,
        ]);
    });

    $queryCount = trackedQueryCount(function (): void {
        $this->getJson('/api/v1/admin/orders', [
            'Accept-Language' => 'en',
        ])->assertOk();
    });

    expect($queryCount)->toBeLessThanOrEqual(5);
});

it('keeps admin order detail reads within a bounded query count without attachment metadata n plus one queries', function () {
    actingAdminWithOrderReadPermissions();

    $order = Order::factory()->create([
        'status' => OrderStatus::CONFIRMED,
        'payment_status' => PaymentStatus::PARTIALLY_PAID,
    ]);

    $items = OrderItem::factory()->count(2)->create([
        'order_id' => $order->getKey(),
        'price_type' => ServicePriceType::FIXED,
    ]);

    foreach ($items as $item) {
        $item->attachments()->createMany([
            [
                'disk' => 'public',
                'path' => 'orders/attachments/'.$item->getKey().'-one.pdf',
                'stored_name' => $item->getKey().'-one.pdf',
                'original_name' => 'one.pdf',
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => 1024,
            ],
            [
                'disk' => 'public',
                'path' => 'orders/attachments/'.$item->getKey().'-two.pdf',
                'stored_name' => $item->getKey().'-two.pdf',
                'original_name' => 'two.pdf',
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size_bytes' => 2048,
            ],
        ]);
    }

    $queryCount = trackedQueryCount(function () use ($order): void {
        $this->getJson('/api/v1/admin/orders/'.$order->getKey(), [
            'Accept-Language' => 'en',
        ])->assertOk();
    });

    expect($queryCount)->toBeLessThanOrEqual(8);
});

it('keeps admin order item list reads within a bounded query count', function () {
    actingAdminWithOrderReadPermissions();

    $order = Order::factory()->create();

    OrderItem::factory()->count(3)->create([
        'order_id' => $order->getKey(),
        'price_type' => ServicePriceType::FIXED,
    ])->each(function (OrderItem $item): void {
        $item->attachments()->create([
            'disk' => 'public',
            'path' => 'orders/attachments/'.$item->getKey().'.pdf',
            'stored_name' => $item->getKey().'.pdf',
            'original_name' => 'attachment.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 2048,
        ]);
    });

    $queryCount = trackedQueryCount(function () use ($order): void {
        $this->getJson('/api/v1/admin/orders/'.$order->getKey().'/items', [
            'Accept-Language' => 'en',
        ])->assertOk();
    });

    expect($queryCount)->toBeLessThanOrEqual(7);
});

it('keeps protected nested attachment metadata reads within a bounded query count', function () {
    actingAdminWithOrderReadPermissions();

    $order = Order::factory()->create();
    $item = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'price_type' => ServicePriceType::FIXED,
    ]);

    $item->attachments()->createMany([
        [
            'disk' => 'public',
            'path' => 'orders/attachments/a.pdf',
            'stored_name' => 'a.pdf',
            'original_name' => 'a.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1000,
        ],
        [
            'disk' => 'public',
            'path' => 'orders/attachments/b.pdf',
            'stored_name' => 'b.pdf',
            'original_name' => 'b.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 1001,
        ],
    ]);

    $queryCount = trackedQueryCount(function () use ($order, $item): void {
        $this->getJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$item->getKey(), [
            'Accept-Language' => 'en',
        ])->assertOk();
    });

    expect($queryCount)->toBeLessThanOrEqual(7);
});
