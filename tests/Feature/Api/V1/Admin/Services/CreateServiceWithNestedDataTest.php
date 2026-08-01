<?php

declare(strict_types=1);

use App\Models\Service;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function nestedServiceAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function nestedServiceAdminToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('creates a service atomically with nested specifications, order fields, pricing options, and media', function () {
    $accessToken = nestedServiceAdminToken();

    $response = $this->postJson('/api/v1/admin/services', [
        'nameAr' => 'خدمة متكاملة',
        'nameEn' => 'Full Service',
        'shortDescriptionAr' => 'وصف مختصر',
        'shortDescriptionEn' => 'Short description',
        'descriptionAr' => 'وصف كامل',
        'descriptionEn' => 'Full description',
        'priceType' => 1,
        'basePrice' => 100,
        'specifications' => [
            [
                'labelAr' => 'الخامة',
                'labelEn' => 'Material',
                'valueAr' => 'قطن',
                'valueEn' => 'Cotton',
            ],
        ],
        'orderFields' => [
            [
                'labelAr' => 'المقاس المطلوب',
                'labelEn' => 'Requested size',
                'isRequired' => true,
            ],
        ],
        'pricingOptions' => [
            [
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
            ],
        ],
        'media' => [
            [
                'file' => UploadedFile::fake()->image('cover.jpg'),
                'type' => 0,
                'isMain' => true,
            ],
        ],
    ], nestedServiceAdminHeaders($accessToken));

    $response->assertCreated()
        ->assertJsonPath('data.nameEn', 'Full Service')
        ->assertJsonCount(1, 'data.specifications')
        ->assertJsonCount(1, 'data.orderFields')
        ->assertJsonCount(1, 'data.pricingOptions')
        ->assertJsonCount(1, 'data.media');

    expect(Service::query()->count())->toBe(1);
});
