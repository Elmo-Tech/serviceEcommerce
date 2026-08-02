<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServiceOrderFieldType;
use App\Enums\Services\ServicePriceType;
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

it('returns nested 404 when an order item update references a foreign order item answer id', function () {
    $accessToken = (string) loginAdminForTests()->json('data.accessToken');
    $headers = [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => 'en',
    ];

    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);
    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
    ]);

    $field = $service->orderFields()->create([
        'label_ar' => 'الاسم',
        'label_en' => 'Name',
        'field_type' => ServiceOrderFieldType::TEXT,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);
    $orderItem = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'service_id' => $service->getKey(),
        'price_type' => $service->price_type,
    ]);
    $orderItem->answers()->create([
        'service_order_field_id' => $field->getKey(),
        'question_ar' => $field->label_ar,
        'question_en' => $field->label_en,
        'field_type' => $field->field_type->value,
        'is_required' => true,
        'answer' => 'Primary',
    ]);

    $foreignOrder = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);
    $foreignItem = OrderItem::factory()->create([
        'order_id' => $foreignOrder->getKey(),
        'service_id' => $service->getKey(),
        'price_type' => $service->price_type,
    ]);
    $foreignAnswer = $foreignItem->answers()->create([
        'service_order_field_id' => $field->getKey(),
        'question_ar' => $field->label_ar,
        'question_en' => $field->label_en,
        'field_type' => $field->field_type->value,
        'is_required' => true,
        'answer' => 'Foreign',
    ]);

    $this->patchJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$orderItem->getKey(), [
        'answers' => [
            [
                'orderItemAnswerId' => $foreignAnswer->getKey(),
                'answer' => 'Updated',
            ],
        ],
    ], $headers)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});

it('rejects omitting a required stored answer snapshot during full replacement', function () {
    $accessToken = (string) loginAdminForTests()->json('data.accessToken');
    $headers = [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => 'en',
    ];

    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);
    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
    ]);

    $requiredField = $service->orderFields()->create([
        'label_ar' => 'المطلوب',
        'label_en' => 'Required',
        'field_type' => ServiceOrderFieldType::TEXT,
        'is_required' => true,
        'sort_order' => 1,
    ]);
    $optionalField = $service->orderFields()->create([
        'label_ar' => 'اختياري',
        'label_en' => 'Optional',
        'field_type' => ServiceOrderFieldType::TEXT,
        'is_required' => false,
        'sort_order' => 2,
    ]);

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);
    $orderItem = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'service_id' => $service->getKey(),
        'price_type' => $service->price_type,
    ]);

    $requiredAnswer = $orderItem->answers()->create([
        'service_order_field_id' => $requiredField->getKey(),
        'question_ar' => $requiredField->label_ar,
        'question_en' => $requiredField->label_en,
        'field_type' => $requiredField->field_type->value,
        'is_required' => true,
        'answer' => 'Required answer',
    ]);
    $optionalAnswer = $orderItem->answers()->create([
        'service_order_field_id' => $optionalField->getKey(),
        'question_ar' => $optionalField->label_ar,
        'question_en' => $optionalField->label_en,
        'field_type' => $optionalField->field_type->value,
        'is_required' => false,
        'answer' => 'Optional answer',
    ]);

    $this->patchJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$orderItem->getKey(), [
        'answers' => [
            [
                'orderItemAnswerId' => $optionalAnswer->getKey(),
                'answer' => 'Optional only',
            ],
        ],
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'REQUIRED_ORDER_FIELD_MISSING');

    expect($requiredAnswer->fresh()?->answer)->toBe('Required answer');
});

it('still updates a stored answer after its current service order field link becomes null', function () {
    $accessToken = (string) loginAdminForTests()->json('data.accessToken');
    $headers = [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => 'en',
    ];

    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);
    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
    ]);

    $field = $service->orderFields()->create([
        'label_ar' => 'المقاس',
        'label_en' => 'Size',
        'field_type' => ServiceOrderFieldType::TEXT,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::UNPAID,
    ]);
    $orderItem = OrderItem::factory()->create([
        'order_id' => $order->getKey(),
        'service_id' => $service->getKey(),
        'price_type' => $service->price_type,
    ]);

    $storedAnswer = $orderItem->answers()->create([
        'service_order_field_id' => $field->getKey(),
        'question_ar' => $field->label_ar,
        'question_en' => $field->label_en,
        'field_type' => $field->field_type->value,
        'is_required' => true,
        'answer' => '100x100',
    ]);

    $field->forceDelete();
    $storedAnswer->refresh();

    expect($storedAnswer->service_order_field_id)->toBeNull();

    $this->patchJson('/api/v1/admin/orders/'.$order->getKey().'/items/'.$orderItem->getKey(), [
        'answers' => [
            [
                'orderItemAnswerId' => $storedAnswer->getKey(),
                'answer' => '250x120',
            ],
        ],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.answers.0.id', $storedAnswer->getKey())
        ->assertJsonPath('data.answers.0.answer', '250x120');
});
