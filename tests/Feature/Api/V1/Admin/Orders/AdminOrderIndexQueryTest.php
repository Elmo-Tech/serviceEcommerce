<?php

declare(strict_types=1);

use App\Enums\Orders\OrderPlace;
use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServicePriceType;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
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
    $this->seed(SuperAdminSeeder::class);
});

function adminOrderIndexHeaders(): array
{
    return [
        'Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'),
        'Accept-Language' => 'en',
    ];
}

it('applies every approved admin order index filter and returns empty results safely', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00'));

    $headers = adminOrderIndexHeaders();

    $matchingCustomer = Customer::factory()->create();
    $otherCustomer = Customer::factory()->create();

    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);

    $matchingService = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
    ]);
    $otherService = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
    ]);

    $matchingOrder = Order::factory()->create([
        'customer_id' => $matchingCustomer->getKey(),
        'customer_name' => 'Target Customer',
        'customer_phone' => '01001234567',
        'customer_email' => 'target@example.com',
        'status' => OrderStatus::CONFIRMED,
        'payment_status' => PaymentStatus::PARTIALLY_PAID,
        'order_place' => OrderPlace::WHATSAPP,
        'subtotal' => '350.00',
        'discount_amount' => '10.00',
        'total' => '340.00',
        'paid_amount' => '100.00',
        'created_at' => Carbon::parse('2026-08-02 09:00:00'),
        'updated_at' => Carbon::parse('2026-08-02 09:00:00'),
    ]);
    $matchingOrder->items()->create([
        'service_id' => $matchingService->getKey(),
        'service_name_ar' => 'خدمة مطابقة',
        'service_name_en' => 'Matching Service',
        'service_slug_ar' => 'خدمة-مطابقة',
        'service_slug_en' => 'matching-service',
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '350.00',
        'unit_price' => '350.00',
        'quantity' => 1,
        'item_total' => '350.00',
        'item_note' => null,
    ]);

    $filteredOutOrder = Order::factory()->create([
        'customer_id' => $otherCustomer->getKey(),
        'customer_name' => 'Other Customer',
        'customer_phone' => '01009998888',
        'customer_email' => 'other@example.com',
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
        'order_place' => OrderPlace::WEBSITE,
        'subtotal' => '900.00',
        'discount_amount' => '0.00',
        'total' => '900.00',
        'paid_amount' => '0.00',
        'created_at' => Carbon::parse('2026-07-20 09:00:00'),
        'updated_at' => Carbon::parse('2026-07-20 09:00:00'),
    ]);
    $filteredOutOrder->items()->create([
        'service_id' => $otherService->getKey(),
        'service_name_ar' => 'خدمة أخرى',
        'service_name_en' => 'Other Service',
        'service_slug_ar' => 'خدمة-أخرى',
        'service_slug_en' => 'other-service',
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '900.00',
        'unit_price' => '900.00',
        'quantity' => 1,
        'item_total' => '900.00',
        'item_note' => null,
    ]);

    $response = $this->getJson(
        '/api/v1/admin/orders?'
        .'filter[search]=Target'
        .'&filter[status]='.OrderStatus::CONFIRMED->value
        .'&filter[paymentStatus]='.PaymentStatus::PARTIALLY_PAID->value
        .'&filter[orderPlace]='.OrderPlace::WHATSAPP->value
        .'&filter[customerId]='.$matchingCustomer->getKey()
        .'&filter[serviceId]='.$matchingService->getKey()
        .'&filter[createdFrom]=2026-08-01 00:00:00'
        .'&filter[createdTo]=2026-08-03 00:00:00'
        .'&filter[totalFrom]=300'
        .'&filter[totalTo]=400',
        $headers,
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $matchingOrder->getKey())
        ->assertJsonPath('data.0.customerName', 'Target Customer')
        ->assertJsonPath('data.0.status', OrderStatus::CONFIRMED->value)
        ->assertJsonPath('data.0.paymentStatus', PaymentStatus::PARTIALLY_PAID->value)
        ->assertJsonPath('data.0.orderPlace', OrderPlace::WHATSAPP->value);

    $emptyResponse = $this->getJson(
        '/api/v1/admin/orders?filter[search]=no-match-anywhere',
        $headers,
    );

    $emptyResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 0)
        ->assertJsonCount(0, 'data');
});

it('supports approved sort combinations and pagination boundaries for admin order index', function () {
    $headers = adminOrderIndexHeaders();
    $sortingCustomer = Customer::factory()->create();

    $lowestTotal = Order::factory()->create([
        'customer_id' => $sortingCustomer->getKey(),
        'customer_name' => 'Sort Boundary Lowest',
        'total' => '100.00',
        'created_at' => Carbon::parse('2026-08-01 08:00:00'),
        'updated_at' => Carbon::parse('2026-08-01 08:00:00'),
    ]);
    $middleTotal = Order::factory()->create([
        'customer_id' => $sortingCustomer->getKey(),
        'customer_name' => 'Sort Boundary Middle',
        'total' => '200.00',
        'created_at' => Carbon::parse('2026-08-02 08:00:00'),
        'updated_at' => Carbon::parse('2026-08-02 08:00:00'),
    ]);
    $highestTotal = Order::factory()->create([
        'customer_id' => $sortingCustomer->getKey(),
        'customer_name' => 'Sort Boundary Highest',
        'total' => '300.00',
        'created_at' => Carbon::parse('2026-08-03 08:00:00'),
        'updated_at' => Carbon::parse('2026-08-03 08:00:00'),
    ]);

    $queryPrefix = '/api/v1/admin/orders?filter[customerId]='.$sortingCustomer->getKey();

    $this->getJson($queryPrefix.'&sort=createdAt&page=1&perPage=2', $headers)
        ->assertOk()
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('meta.perPage', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.createdAt', '2026-08-01T05:00:00.000000Z')
        ->assertJsonPath('data.1.createdAt', '2026-08-02T05:00:00.000000Z');

    $this->getJson($queryPrefix.'&sort=createdAt&page=2&perPage=2', $headers)
        ->assertOk()
        ->assertJsonPath('meta.currentPage', 2)
        ->assertJsonPath('meta.perPage', 2)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.createdAt', '2026-08-03T05:00:00.000000Z');

    $this->getJson($queryPrefix.'&sort=-createdAt&page=1&perPage=2', $headers)
        ->assertOk()
        ->assertJsonPath('data.0.createdAt', '2026-08-03T05:00:00.000000Z')
        ->assertJsonPath('data.1.createdAt', '2026-08-02T05:00:00.000000Z');

    $this->getJson($queryPrefix.'&sort=total&page=1&perPage=2', $headers)
        ->assertOk()
        ->assertJsonPath('data.0.total', '100.00')
        ->assertJsonPath('data.1.total', '200.00');

    $this->getJson($queryPrefix.'&sort=-total&page=1&perPage=2', $headers)
        ->assertOk()
        ->assertJsonPath('data.0.total', '300.00')
        ->assertJsonPath('data.1.total', '200.00');

    $this->getJson('/api/v1/admin/orders?perPage=100', $headers)
        ->assertOk()
        ->assertJsonPath('meta.perPage', 100);

    $this->getJson('/api/v1/admin/orders?perPage=101', $headers)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});
