<?php

declare(strict_types=1);

use App\Models\Service;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
});

function serviceComponentsAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function serviceComponentsAdminToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('manages service specifications, order fields, and pricing options with nested value actions', function () {
    $accessToken = serviceComponentsAdminToken();
    $service = Service::factory()->startFrom()->create(['is_active' => false]);

    $specificationResponse = $this->postJson("/api/v1/admin/services/{$service->getKey()}/specifications", [
        'labelAr' => 'اللون',
        'labelEn' => 'Color',
        'valueAr' => 'أبيض',
        'valueEn' => 'White',
    ], serviceComponentsAdminHeaders($accessToken));

    $specificationResponse->assertCreated()
        ->assertJsonPath('data.labelEn', 'Color');

    $specificationId = (int) $specificationResponse->json('data.id');

    $this->patchJson("/api/v1/admin/services/{$service->getKey()}/specifications/{$specificationId}", [
        'valueEn' => 'Ivory',
    ], serviceComponentsAdminHeaders($accessToken))
        ->assertOk()
        ->assertJsonPath('data.valueEn', 'Ivory');

    $this->getJson("/api/v1/admin/services/{$service->getKey()}/specifications", serviceComponentsAdminHeaders($accessToken))
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $orderFieldResponse = $this->postJson("/api/v1/admin/services/{$service->getKey()}/order-fields", [
        'labelAr' => 'ملاحظات',
        'labelEn' => 'Notes',
        'isRequired' => true,
    ], serviceComponentsAdminHeaders($accessToken));

    $orderFieldResponse->assertCreated()
        ->assertJsonPath('data.labelEn', 'Notes')
        ->assertJsonPath('data.isRequired', true);

    $pricingOptionResponse = $this->postJson("/api/v1/admin/services/{$service->getKey()}/pricing-options", [
        'nameAr' => 'المقاس',
        'nameEn' => 'Size',
        'inputType' => 0,
        'isRequired' => true,
        'values' => [
            [
                'labelAr' => 'صغير',
                'labelEn' => 'Small',
                'priceAdjustment' => 10,
                'isActive' => true,
            ],
        ],
    ], serviceComponentsAdminHeaders($accessToken));

    $pricingOptionResponse->assertCreated()
        ->assertJsonPath('data.nameEn', 'Size')
        ->assertJsonCount(1, 'data.values');

    $pricingOptionId = (int) $pricingOptionResponse->json('data.id');
    $pricingValueId = (int) $pricingOptionResponse->json('data.values.0.id');

    $this->patchJson("/api/v1/admin/services/{$service->getKey()}/pricing-options/{$pricingOptionId}", [
        'values' => [
            [
                'id' => $pricingValueId,
                'actionStatus' => 'update',
                'labelEn' => 'Small Updated',
                'priceAdjustment' => 15,
                'isActive' => true,
            ],
            [
                'actionStatus' => 'create',
                'labelAr' => 'كبير',
                'labelEn' => 'Large',
                'priceAdjustment' => 30,
                'isActive' => true,
            ],
            [
                'id' => $pricingValueId,
                'actionStatus' => '',
            ],
        ],
    ], serviceComponentsAdminHeaders($accessToken))
        ->assertOk()
        ->assertJsonCount(2, 'data.values');

    $this->deleteJson("/api/v1/admin/services/{$service->getKey()}/specifications/{$specificationId}", [], serviceComponentsAdminHeaders($accessToken))
        ->assertOk();

    $this->deleteJson("/api/v1/admin/services/{$service->getKey()}/pricing-options/{$pricingOptionId}", [], serviceComponentsAdminHeaders($accessToken))
        ->assertOk();
});
