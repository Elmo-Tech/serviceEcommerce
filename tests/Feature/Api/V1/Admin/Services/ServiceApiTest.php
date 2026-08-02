<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceOrderField;
use App\Models\ServicePricingOption;
use App\Models\ServicePricingOptionValue;
use App\Models\ServiceSpecification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
});

function serviceAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function serviceAdminToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('manages admin services with localized listing, slug stability, restore cleanup, and safe deletion guards', function () {
    $accessToken = serviceAdminToken();

    $rootCategory = Category::factory()->root()->create([
        'name_ar' => 'التصنيف الرئيسي',
        'name_en' => 'Root Category',
        'slug_ar' => 'التصنيف-الرئيسي',
        'slug_en' => 'root-category',
    ]);
    $subcategory = Category::factory()->subcategory($rootCategory)->create([
        'name_ar' => 'تصنيف فرعي',
        'name_en' => 'Subcategory',
        'slug_ar' => 'تصنيف-فرعي',
        'slug_en' => 'subcategory',
    ]);

    $createResponse = $this->postJson('/api/v1/admin/services', [
        'categoryId' => $rootCategory->getKey(),
        'subcategoryId' => $subcategory->getKey(),
        'nameAr' => '  خدمة   التنظيف  ',
        'nameEn' => '  Cleaning   Service  ',
        'shortDescriptionAr' => '  وصف مختصر عربي  ',
        'shortDescriptionEn' => '  English short description  ',
        'descriptionAr' => '  وصف عربي كامل  ',
        'descriptionEn' => '  Full english description  ',
        'priceType' => 0,
        'basePrice' => 250,
        'isActive' => true,
        'isAvailable' => true,
    ], serviceAdminHeaders($accessToken, 'en'));

    $createResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.categoryId', $rootCategory->getKey())
        ->assertJsonPath('data.subcategoryId', $subcategory->getKey())
        ->assertJsonPath('data.priceType', 0)
        ->assertJsonPath('data.isActive', true)
        ->assertJsonPath('data.isAvailable', true);

    $service = Service::query()->firstOrFail();
    expect(trim((string) $service->name_ar))->not->toBe('');
    expect(trim((string) $service->name_en))->not->toBe('');
    expect((float) $createResponse->json('data.basePrice'))->toBe(250.0);
    $originalArabicSlug = $service->slug_ar;
    $originalEnglishSlug = $service->slug_en;

    Service::factory()->categorized($rootCategory)->create([
        'name_ar' => 'خدمة إضافية',
        'name_en' => 'Extra Service',
        'short_description_ar' => 'وصف إضافي',
        'short_description_en' => 'Extra short description',
        'slug_ar' => 'خدمة-إضافية',
        'slug_en' => 'extra-service',
        'is_active' => true,
    ]);

    $listResponse = $this->getJson(
        '/api/v1/admin/services?filter[search]=Service&filter[categoryId]='.$rootCategory->getKey().'&filter[isActive]=true&filter[trashed]=without&sort=createdAt&page=1&perPage=20',
        serviceAdminHeaders($accessToken, 'en'),
    );

    $listResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.currentPage', 1);

    expect(collect($listResponse->json('data'))->pluck('name')->filter()->count())->toBeGreaterThanOrEqual(2);

    $showArabicResponse = $this->getJson(
        '/api/v1/admin/services/'.$service->getKey(),
        serviceAdminHeaders($accessToken, 'ar'),
    );

    $showArabicResponse->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('data.slugAr', $originalArabicSlug)
        ->assertJsonPath('data.slugEn', $originalEnglishSlug);

    $updateResponse = $this->patchJson(
        '/api/v1/admin/services/'.$service->getKey(),
        [
            'categoryId' => null,
            'nameAr' => 'خدمة التنظيف المحدثة',
            'nameEn' => 'Updated Cleaning Service',
        ],
        serviceAdminHeaders($accessToken, 'en'),
    );

    $updateResponse->assertOk()
        ->assertJsonPath('data.categoryId', null)
        ->assertJsonPath('data.subcategoryId', null)
        ->assertJsonPath('data.slugAr', $originalArabicSlug)
        ->assertJsonPath('data.slugEn', $originalEnglishSlug);

    expect(trim((string) $service->fresh()->name_ar))->not->toBe('');
    expect(trim((string) $service->fresh()->name_en))->not->toBe('');

    $serviceWithChildren = Service::factory()->create();
    ServiceSpecification::factory()->create(['service_id' => $serviceWithChildren->getKey()]);
    ServiceOrderField::factory()->create(['service_id' => $serviceWithChildren->getKey()]);
    $option = ServicePricingOption::factory()->create(['service_id' => $serviceWithChildren->getKey()]);
    ServicePricingOptionValue::factory()->create(['service_pricing_option_id' => $option->getKey()]);

    $this->deleteJson(
        '/api/v1/admin/services/'.$serviceWithChildren->getKey(),
        [],
        serviceAdminHeaders($accessToken, 'en'),
    )->assertOk();

    expect($serviceWithChildren->fresh()?->trashed())->toBeTrue()
        ->and(ServiceSpecification::query()->where('service_id', $serviceWithChildren->getKey())->exists())->toBeTrue()
        ->and(ServiceOrderField::query()->where('service_id', $serviceWithChildren->getKey())->exists())->toBeTrue()
        ->and(ServicePricingOption::query()->where('service_id', $serviceWithChildren->getKey())->exists())->toBeTrue();

    $inactiveCategory = Category::factory()->root()->inactive()->create();
    $restoreTarget = Service::factory()->categorized($inactiveCategory)->deleted()->create([
        'category_id' => $inactiveCategory->getKey(),
        'subcategory_id' => null,
    ]);

    $restoreResponse = $this->postJson(
        '/api/v1/admin/services/'.$restoreTarget->getKey().'/restore',
        [],
        serviceAdminHeaders($accessToken, 'en'),
    );

    $restoreResponse->assertOk()
        ->assertJsonPath('data.id', $restoreTarget->getKey())
        ->assertJsonPath('data.categoryId', $inactiveCategory->getKey())
        ->assertJsonPath('data.isActive', false)
        ->assertJsonPath('data.deletedAt', null);

    $categoryWithService = Category::factory()->root()->create();
    Service::factory()->categorized($categoryWithService)->create();

    $this->deleteJson(
        '/api/v1/admin/categories/'.$categoryWithService->getKey(),
        [],
        serviceAdminHeaders($accessToken, 'en'),
    )
        ->assertConflict()
        ->assertJsonPath('code', 'CATEGORY_HAS_SERVICES');

    $rootWithSubService = Category::factory()->root()->create();
    $subWithService = Category::factory()->subcategory($rootWithSubService)->create();
    Service::factory()->underSubcategory($rootWithSubService, $subWithService)->create();

    $this->deleteJson(
        '/api/v1/admin/categories/'.$rootWithSubService->getKey().'/subcategories/'.$subWithService->getKey(),
        [],
        serviceAdminHeaders($accessToken, 'en'),
    )
        ->assertConflict()
        ->assertJsonPath('code', 'SUBCATEGORY_HAS_SERVICES');
});

it('rejects invalid admin service payloads and preserves query allow-lists', function () {
    $accessToken = serviceAdminToken();

    $this->postJson('/api/v1/admin/services', [
        'nameAr' => 'خدمة',
        'nameEn' => 'Service',
        'priceType' => 0,
    ], serviceAdminHeaders($accessToken, 'en'))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $this->getJson(
        '/api/v1/admin/services?search=wrong&sortBy=name',
        serviceAdminHeaders($accessToken, 'en'),
    )
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['payload']]);
});

it('accepts boolean-like string values for service activation flags during create', function () {
    $accessToken = serviceAdminToken();

    $rootCategory = Category::factory()->root()->create();

    $response = $this->post('/api/v1/admin/services', [
        'categoryId' => (string) $rootCategory->getKey(),
        'nameAr' => 'خدمة طباعة',
        'nameEn' => 'Printing Service',
        'shortDescriptionAr' => 'وصف مختصر',
        'shortDescriptionEn' => 'Short description',
        'descriptionAr' => 'وصف كامل',
        'descriptionEn' => 'Full description',
        'priceType' => '0',
        'basePrice' => '500',
        'isActive' => 'true',
        'isAvailable' => 'false',
    ], serviceAdminHeaders($accessToken, 'en'));

    $response->assertCreated()
        ->assertJsonPath('data.isActive', true)
        ->assertJsonPath('data.isAvailable', false);
});

it('returns the approved authentication and permission boundaries for admin service routes', function () {
    $this->getJson('/api/v1/admin/services', [
        'Accept-Language' => 'en',
    ])->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $this->seed(RolesAndPermissionsSeeder::class);

    $inactiveAdmin = User::factory()->administrator()->inactive()->create([
        'email' => 'inactive-service-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    Sanctum::actingAs($inactiveAdmin);

    $this->getJson('/api/v1/admin/services', [
        'Accept-Language' => 'en',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'USER_INACTIVE');

    $forbiddenAdmin = User::factory()->administrator()->create([
        'email' => 'forbidden-service-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    Sanctum::actingAs($forbiddenAdmin);

    $this->getJson('/api/v1/admin/services', [
        'Accept-Language' => 'en',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
