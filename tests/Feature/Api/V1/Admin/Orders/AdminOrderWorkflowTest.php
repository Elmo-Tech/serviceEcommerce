<?php

declare(strict_types=1);

use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServicePriceType;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\OrdersPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
});

function orderWorkflowHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function orderWorkflowToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('lists orders with approved filters and shows one localized order detail', function () {
    $accessToken = orderWorkflowToken();

    $matchingOrder = Order::factory()->create([
        'customer_name' => 'Mohamed Hassan',
        'customer_phone' => '01001234567',
        'status' => OrderStatus::PENDING,
        'order_place' => OrderPlace::WEBSITE,
        'payment_status' => PaymentStatus::UNPAID,
        'subtotal' => '250.00',
        'discount_amount' => '0.00',
        'total' => '250.00',
        'paid_amount' => '0.00',
    ]);
    $matchingOrder->items()->create([
        'service_id' => null,
        'service_name_ar' => 'خدمة',
        'service_name_en' => 'Service',
        'service_slug_ar' => 'خدمة',
        'service_slug_en' => 'service',
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '250.00',
        'unit_price' => '250.00',
        'quantity' => 1,
        'item_total' => '250.00',
        'item_note' => null,
    ]);

    Order::factory()->create([
        'customer_name' => 'Other Customer',
        'status' => OrderStatus::CONFIRMED,
    ]);

    $listResponse = $this->getJson(
        '/api/v1/admin/orders?filter[status]=0&filter[search]=Mohamed&sort=-createdAt&page=1&perPage=15',
        orderWorkflowHeaders($accessToken, 'en'),
    );

    $listResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.perPage', 15)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $matchingOrder->getKey())
        ->assertJsonPath('data.0.customerName', 'Mohamed Hassan')
        ->assertJsonPath('data.0.status', OrderStatus::PENDING->value);

    $showResponse = $this->getJson('/api/v1/admin/orders/'.$matchingOrder->getKey(), orderWorkflowHeaders($accessToken, 'en'));

    $showResponse->assertOk()
        ->assertJsonPath('data.id', $matchingOrder->getKey())
        ->assertJsonPath('data.customerSnapshot.name', 'Mohamed Hassan')
        ->assertJsonPath('data.payment.paymentStatus', PaymentStatus::UNPAID->value)
        ->assertJsonPath('data.items.0.serviceSnapshot.name', 'Service');
});

it('changes order status with valid transitions and requires a cancellation reason', function () {
    $accessToken = orderWorkflowToken();

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);

    $confirmResponse = $this->patchJson(
        '/api/v1/admin/orders/'.$order->getKey().'/status',
        ['status' => OrderStatus::CONFIRMED->value],
        orderWorkflowHeaders($accessToken, 'en'),
    );

    $confirmResponse->assertOk()
        ->assertJsonPath('data.status', OrderStatus::CONFIRMED->value);

    $cancelWithoutReason = $this->patchJson(
        '/api/v1/admin/orders/'.$order->getKey().'/status',
        ['status' => OrderStatus::CANCELLED->value],
        orderWorkflowHeaders($accessToken, 'en'),
    );

    $cancelWithoutReason->assertUnprocessable()
        ->assertJsonPath('code', 'CANCELLATION_REASON_REQUIRED');

    $cancelWithReason = $this->patchJson(
        '/api/v1/admin/orders/'.$order->getKey().'/status',
        ['status' => OrderStatus::CANCELLED->value, 'reason' => 'Customer requested cancellation'],
        orderWorkflowHeaders($accessToken, 'en'),
    );

    $cancelWithReason->assertOk()
        ->assertJsonPath('data.status', OrderStatus::CANCELLED->value)
        ->assertJsonPath('data.cancellation.reason', 'Customer requested cancellation');
});

it('shows and updates the admin payment summary and enforces manage-payment permission separately', function () {
    $accessToken = orderWorkflowToken();

    $order = Order::factory()->create([
        'status' => OrderStatus::CONFIRMED,
        'total' => '400.00',
        'paid_amount' => '0.00',
        'payment_status' => PaymentStatus::UNPAID,
    ]);

    $showResponse = $this->getJson('/api/v1/admin/orders/'.$order->getKey().'/payment', orderWorkflowHeaders($accessToken, 'en'));

    $showResponse->assertOk()
        ->assertJsonPath('data.total', '400.00')
        ->assertJsonPath('data.paymentStatus', PaymentStatus::UNPAID->value)
        ->assertJsonPath('data.remainingAmount', '400.00');

    $updateResponse = $this->patchJson(
        '/api/v1/admin/orders/'.$order->getKey().'/payment',
        ['paidAmount' => '150.00'],
        orderWorkflowHeaders($accessToken, 'en'),
    );

    $updateResponse->assertOk()
        ->assertJsonPath('data.paymentStatus', PaymentStatus::PARTIALLY_PAID->value)
        ->assertJsonPath('data.paidAmount', '150.00')
        ->assertJsonPath('data.remainingAmount', '250.00');

    $forbiddenAdmin = User::factory()->administrator()->create([
        'email' => 'orders-view-only@example.test',
        'password' => Hash::make('Password123!'),
    ]);
    $forbiddenAdmin->givePermissionTo('orders.view');
    Sanctum::actingAs($forbiddenAdmin);

    $this->getJson('/api/v1/admin/orders/'.$order->getKey().'/payment', [
        'Accept-Language' => 'en',
    ])->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
