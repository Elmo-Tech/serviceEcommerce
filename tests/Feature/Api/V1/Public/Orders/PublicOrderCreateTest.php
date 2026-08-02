<?php

declare(strict_types=1);

use App\Enums\Orders\OrderStatus;
use App\Enums\Orders\PaymentStatus;
use App\Enums\Services\ServicePriceType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates a public order and replays the same idempotency key with 200', function () {
    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);

    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '150.00',
    ]);

    $headers = [
        'Accept-Language' => 'en',
        'Idempotency-Key' => (string) Str::uuid(),
    ];

    $payload = [
        'customer' => [
            'name' => 'Mohamed Hassan',
            'email' => 'mohamed@example.com',
            'phone' => '01001234567',
        ],
        'address' => [
            'province' => 'Cairo',
            'city' => 'Nasr City',
            'address' => 'Building 10',
        ],
        'customerNote' => 'Call before arrival',
        'items' => [
            [
                'serviceId' => $service->getKey(),
                'quantity' => 2,
            ],
        ],
    ];

    $createdResponse = $this->postJson('/api/v1/public/orders', $payload, $headers);

    $createdResponse->assertCreated()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', OrderStatus::PENDING->value)
        ->assertJsonPath('data.paymentStatus', PaymentStatus::UNPAID->value)
        ->assertJsonPath('data.subtotal', '300.00')
        ->assertJsonPath('data.discountAmount', '0.00')
        ->assertJsonPath('data.total', '300.00')
        ->assertJsonPath('data.paidAmount', '0.00')
        ->assertJsonPath('data.remainingAmount', '300.00');

    $orderNumber = $createdResponse->json('data.orderNumber');

    $replayResponse = $this->postJson('/api/v1/public/orders', $payload, $headers);

    $replayResponse->assertOk()
        ->assertJsonPath('data.orderNumber', $orderNumber)
        ->assertJsonPath('data.total', '300.00');

    expect(Order::query()->count())->toBe(1);
});

it('rate limits public order creation after five requests per minute for the same ip and normalized phone', function () {
    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);

    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
        'price_type' => ServicePriceType::FIXED,
        'base_price' => '50.00',
    ]);

    $payload = [
        'customer' => [
            'name' => 'Rate Limited Customer',
            'email' => 'rate@example.com',
            'phone' => '0100 123 4567',
        ],
        'items' => [
            [
                'serviceId' => $service->getKey(),
                'quantity' => 1,
            ],
        ],
    ];

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->postJson('/api/v1/public/orders', $payload, [
            'Accept-Language' => 'en',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();
    }

    $this->postJson('/api/v1/public/orders', $payload, [
        'Accept-Language' => 'en',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertStatus(429)
        ->assertJsonPath('code', 'RATE_LIMITED');
});
