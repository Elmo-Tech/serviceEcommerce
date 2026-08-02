<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServiceOrderFieldType;
use App\Enums\Services\ServicePriceType;
use App\Enums\Services\ServicePricingInputType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use Database\Seeders\OrdersPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(OrdersPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);
});

function orderItemHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function orderItemToken(): string
{
    return (string) loginAdminForTests()->json('data.accessToken');
}

it('lists shows creates and deletes nested order items with strict ownership and final-item protection', function () {
    $accessToken = orderItemToken();

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);

    $existingItem = OrderItem::factory()->for($order)->create();

    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);
    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '125.00',
    ]);

    $this->getJson('/api/v1/admin/orders/'.$order->getKey().'/items', orderItemHeaders($accessToken))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $existingItem->getKey());

    $createResponse = $this->postJson('/api/v1/admin/orders/'.$order->getKey().'/items', [
        'serviceId' => $service->getKey(),
        'quantity' => 2,
        'itemNote' => 'Extra item',
    ], orderItemHeaders($accessToken));

    $createResponse->assertCreated()
        ->assertJsonPath('data.quantity', 2)
        ->assertJsonPath('data.unitPrice', '125.00')
        ->assertJsonPath('data.itemTotal', '250.00');

    $newItemId = (int) $createResponse->json('data.id');

    $this->getJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$newItemId, orderItemHeaders($accessToken))
        ->assertOk()
        ->assertJsonPath('data.id', $newItemId);

    $foreignOrder = Order::factory()->create();
    $foreignItem = OrderItem::factory()->for($foreignOrder)->create();

    $this->getJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$foreignItem->getKey(), orderItemHeaders($accessToken))
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->deleteJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$newItemId, [], orderItemHeaders($accessToken))
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->deleteJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$existingItem->getKey(), [], orderItemHeaders($accessToken))
        ->assertConflict()
        ->assertJsonPath('code', 'ORDER_REQUIRES_AT_LEAST_ONE_ITEM');
});

it('updates quantity selected options and stored answers on an existing order item', function () {
    $accessToken = orderItemToken();

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
        'subtotal' => '100.00',
        'total' => '100.00',
    ]);

    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);
    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::START_FROM,
        'base_price' => '100.00',
    ]);

    $pricingOption = $service->pricingOptions()->create([
        'name_ar' => 'الحجم',
        'name_en' => 'Size',
        'input_type' => ServicePricingInputType::SELECT,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $oldValue = $pricingOption->values()->create([
        'label_ar' => 'صغير',
        'label_en' => 'Small',
        'price_adjustment' => '0.00',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $newValue = $pricingOption->values()->create([
        'label_ar' => 'كبير',
        'label_en' => 'Large',
        'price_adjustment' => '50.00',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $orderField = $service->orderFields()->create([
        'label_ar' => 'المقاس',
        'label_en' => 'Dimensions',
        'field_type' => ServiceOrderFieldType::TEXT,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $orderItem = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'service_id' => $service->getKey(),
        'service_name_ar' => $service->name_ar,
        'service_name_en' => $service->name_en,
        'service_slug_ar' => $service->slug_ar,
        'service_slug_en' => $service->slug_en,
        'price_type' => $service->price_type,
        'base_price' => '100.00',
        'unit_price' => '100.00',
        'quantity' => 1,
        'item_total' => '100.00',
    ]);

    $selectedOption = $orderItem->selectedOptions()->create([
        'pricing_option_id' => $pricingOption->getKey(),
        'option_name_ar' => $pricingOption->name_ar,
        'option_name_en' => $pricingOption->name_en,
        'input_type' => $pricingOption->input_type->value,
        'is_required' => true,
    ]);

    $selectedOption->values()->create([
        'pricing_option_value_id' => $oldValue->getKey(),
        'value_label_ar' => $oldValue->label_ar,
        'value_label_en' => $oldValue->label_en,
        'price_adjustment' => $oldValue->price_adjustment,
    ]);

    $storedAnswer = $orderItem->answers()->create([
        'service_order_field_id' => $orderField->getKey(),
        'question_ar' => $orderField->label_ar,
        'question_en' => $orderField->label_en,
        'field_type' => $orderField->field_type->value,
        'is_required' => true,
        'answer' => '100x100',
    ]);

    $response = $this->patchJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$orderItem->getKey(), [
        'quantity' => 2,
        'selectedOptions' => [
            [
                'pricingOptionId' => $pricingOption->getKey(),
                'valueIds' => [$newValue->getKey()],
            ],
        ],
        'answers' => [
            [
                'orderItemAnswerId' => $storedAnswer->getKey(),
                'answer' => '250x120',
            ],
        ],
        'itemNote' => 'Updated note',
    ], orderItemHeaders($accessToken));

    $response->assertOk()
        ->assertJsonPath('data.quantity', 2)
        ->assertJsonPath('data.unitPrice', '150.00')
        ->assertJsonPath('data.itemTotal', '300.00')
        ->assertJsonPath('data.itemNote', 'Updated note')
        ->assertJsonPath('data.selectedOptions.0.values.0.pricingOptionValueId', $newValue->getKey())
        ->assertJsonPath('data.answers.0.id', $storedAnswer->getKey())
        ->assertJsonPath('data.answers.0.answer', '250x120');
});
