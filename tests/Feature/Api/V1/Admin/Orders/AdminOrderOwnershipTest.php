<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServicePriceType;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\OrdersPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
    Storage::fake(config('filesystems.default'));
});

function adminOrderOwnershipHeaders(): array
{
    return [
        'Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'),
        'Accept-Language' => 'en',
    ];
}

it('keeps nested item lists scoped to their parent order only', function () {
    $headers = adminOrderOwnershipHeaders();

    $firstOrder = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);
    $secondOrder = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);

    $firstItem = OrderItem::factory()->create([
        'order_id' => $firstOrder->getKey(),
        'price_type' => ServicePriceType::FIXED,
    ]);
    OrderItem::factory()->create([
        'order_id' => $secondOrder->getKey(),
        'price_type' => ServicePriceType::FIXED,
    ]);

    $this->getJson('/api/v1/admin/orders/'.$firstOrder->getKey().'/items', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $firstItem->getKey());
});

it('returns non disclosing nested 404 responses for foreign order items and attachments', function () {
    $headers = adminOrderOwnershipHeaders();

    $firstOrder = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);
    $secondOrder = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);

    $firstItem = OrderItem::factory()->create([
        'order_id' => $firstOrder->getKey(),
        'price_type' => ServicePriceType::FIXED,
    ]);
    $foreignItem = OrderItem::factory()->create([
        'order_id' => $secondOrder->getKey(),
        'price_type' => ServicePriceType::FIXED,
    ]);

    Storage::disk(config('filesystems.default'))->put('orders/attachments/foreign.pdf', 'attachment-body');

    $foreignAttachment = $foreignItem->attachments()->create([
        'disk' => config('filesystems.default'),
        'path' => 'orders/attachments/foreign.pdf',
        'stored_name' => 'foreign.pdf',
        'original_name' => 'foreign.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 64,
    ]);

    $this->getJson(
        '/api/v1/admin/orders/'.$firstOrder->getKey().'/items/'.$foreignItem->getKey(),
        $headers,
    )->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->get(
        '/api/v1/admin/orders/'.$firstOrder->getKey().'/items/'.$firstItem->getKey().'/attachments/'.$foreignAttachment->getKey().'/download',
        $headers,
    )->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->deleteJson(
        '/api/v1/admin/orders/'.$firstOrder->getKey().'/items/'.$firstItem->getKey().'/attachments/'.$foreignAttachment->getKey(),
        [],
        $headers,
    )->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
