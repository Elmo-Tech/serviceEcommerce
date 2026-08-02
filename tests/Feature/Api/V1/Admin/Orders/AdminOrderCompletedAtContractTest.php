<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
});

function completedAtAdminHeaders(): array
{
    return [
        'Authorization' => 'Bearer '.(string) loginAdminForTests()->json('data.accessToken'),
        'Accept-Language' => 'en',
    ];
}

it('exposes completedAt as a nullable UTC value on admin index and show', function (): void {
    $completed = Order::factory()->create([
        'status' => OrderStatus::COMPLETED,
        'completed_at' => '2026-08-02 10:30:00',
    ]);
    $pending = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'completed_at' => null,
    ]);
    $headers = completedAtAdminHeaders();

    $this->getJson('/api/v1/admin/orders?sort=createdAt', $headers)
        ->assertOk()
        ->assertJsonFragment([
            'orderNumber' => $completed->order_number,
            'completedAt' => '2026-08-02T10:30:00.000000Z',
        ])
        ->assertJsonFragment([
            'orderNumber' => $pending->order_number,
            'completedAt' => null,
        ]);

    $this->getJson('/api/v1/admin/orders/'.$completed->getKey(), $headers)
        ->assertOk()
        ->assertJsonPath('data.completedAt', '2026-08-02T10:30:00.000000Z');
});

it('rejects completedAt from every order mutation request', function (): void {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->create(['order_id' => $order->getKey()]);
    $headers = completedAtAdminHeaders();
    $value = '2026-08-02T10:30:00Z';

    $responses = [
        $this->postJson('/api/v1/admin/orders', ['completedAt' => $value], $headers),
        $this->patchJson('/api/v1/admin/orders/'.$order->getKey(), ['completedAt' => $value], $headers),
        $this->patchJson('/api/v1/admin/orders/'.$order->getKey().'/status', [
            'status' => OrderStatus::CONFIRMED->value,
            'completedAt' => $value,
        ], $headers),
        $this->patchJson('/api/v1/admin/orders/'.$order->getKey().'/payment', [
            'paidAmount' => '0.00',
            'completedAt' => $value,
        ], $headers),
        $this->postJson('/api/v1/admin/orders/'.$order->getKey().'/items', ['completedAt' => $value], $headers),
        $this->patchJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$item->getKey(), ['completedAt' => $value], $headers),
        $this->postJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$item->getKey().'/attachments', ['completedAt' => $value], $headers),
        $this->postJson('/api/v1/public/orders', ['completedAt' => $value], [
            'Accept-Language' => 'en',
            'Idempotency-Key' => (string) Str::uuid(),
        ]),
    ];

    foreach ($responses as $response) {
        $response->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['completedAt']]);
    }
});
