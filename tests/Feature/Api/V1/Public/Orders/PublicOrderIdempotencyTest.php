<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderIdempotencyKey;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('replays the same public request with the same idempotency key and rejects a conflicting payload reuse', function () {
    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);

    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
    ]);

    $headers = [
        'Accept-Language' => 'en',
        'Idempotency-Key' => (string) Str::uuid(),
    ];

    $payload = [
        'customer' => [
            'name' => 'Idempotent Customer',
            'email' => 'idempotent@example.com',
            'phone' => '01001234567',
        ],
        'items' => [
            [
                'serviceId' => $service->getKey(),
                'quantity' => 1,
            ],
        ],
    ];

    $createdResponse = $this->postJson('/api/v1/public/orders', $payload, $headers);

    $createdResponse->assertCreated()
        ->assertJsonPath('success', true);

    $orderNumber = $createdResponse->json('data.orderNumber');

    $this->postJson('/api/v1/public/orders', $payload, $headers)
        ->assertOk()
        ->assertJsonPath('data.orderNumber', $orderNumber);

    $conflictingPayload = $payload;
    $conflictingPayload['customer']['name'] = 'Different Payload';

    $this->postJson('/api/v1/public/orders', $conflictingPayload, $headers)
        ->assertConflict()
        ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');

    expect(Order::query()->count())->toBe(1)
        ->and(OrderIdempotencyKey::query()->count())->toBe(1)
        ->and(OrderIdempotencyKey::query()->first()?->completed_at)->not->toBeNull();
});

it('does not leave a committed idempotency reservation when public order creation fails', function () {
    $headers = [
        'Accept-Language' => 'en',
        'Idempotency-Key' => (string) Str::uuid(),
    ];

    $payload = [
        'customer' => [
            'name' => 'Broken Order',
            'email' => 'broken@example.com',
            'phone' => '01001234567',
        ],
        'items' => [
            [
                'serviceId' => 999999,
                'quantity' => 1,
            ],
        ],
    ];

    $this->postJson('/api/v1/public/orders', $payload, $headers)
        ->assertNotFound()
        ->assertJsonPath('code', 'SERVICE_NOT_FOUND');

    expect(Order::query()->count())->toBe(0)
        ->and(OrderIdempotencyKey::query()->count())->toBe(0);
});

it('rejects a non uuid idempotency key before public order creation starts', function () {
    $category = Category::factory()->root()->create(['is_active' => true]);
    $subcategory = Category::factory()->subcategory($category)->create(['is_active' => true]);

    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'is_active' => true,
        'is_available' => true,
    ]);

    $this->postJson('/api/v1/public/orders', [
        'customer' => [
            'name' => 'Invalid Header Customer',
            'email' => 'invalid-header@example.com',
            'phone' => '01001234567',
        ],
        'items' => [
            [
                'serviceId' => $service->getKey(),
                'quantity' => 1,
            ],
        ],
    ], [
        'Accept-Language' => 'en',
        'Idempotency-Key' => 'not-a-uuid',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    expect(Order::query()->count())->toBe(0)
        ->and(OrderIdempotencyKey::query()->count())->toBe(0);
});
