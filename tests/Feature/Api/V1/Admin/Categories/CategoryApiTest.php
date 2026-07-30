<?php

declare(strict_types=1);

use App\Models\Category;
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

function categoryAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function categoryAdminToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('manages root categories with localized indexes, search, slug stability, reorder, restore, and safe delete envelopes', function () {
    $accessToken = categoryAdminToken();

    $createResponse = $this->postJson('/api/v1/admin/categories', [
        'nameAr' => '  خدمات   المنزل  ',
        'nameEn' => '  Home   Services  ',
        'descriptionAr' => '  وصف عربي  ',
        'descriptionEn' => '  English description  ',
        'sortOrder' => 5,
        'isActive' => true,
    ], categoryAdminHeaders($accessToken, 'en'));

    $createResponse->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.nameAr', 'خدمات المنزل')
        ->assertJsonPath('data.nameEn', 'Home Services')
        ->assertJsonPath('data.descriptionAr', 'وصف عربي')
        ->assertJsonPath('data.descriptionEn', 'English description')
        ->assertJsonPath('data.subcategoriesCount', 0)
        ->assertJsonPath('data.isActive', true);

    $createdCategory = Category::query()->roots()->sole();
    $originalArabicSlug = $createdCategory->slug_ar;
    $originalEnglishSlug = $createdCategory->slug_en;

    Category::factory()->root()->create([
        'name_ar' => 'العناية المنزلية',
        'name_en' => 'Home Care',
        'description_ar' => 'خدمات إضافية',
        'description_en' => 'Extra services',
        'slug_ar' => 'العناية-المنزلية',
        'slug_en' => 'home-care',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $listResponse = $this->getJson(
        '/api/v1/admin/categories?filter[search]=home&filter[isActive]=true&filter[trashed]=without&sort=name&page=1&perPage=20',
        categoryAdminHeaders($accessToken, 'en'),
    );

    $listResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.currentPage', 1)
        ->assertJsonPath('data.0.name', 'Home Care')
        ->assertJsonPath('data.1.name', 'Home Services')
        ->assertJsonMissingPath('data.0.nameAr')
        ->assertJsonMissingPath('data.0.slugAr');

    $showArabicResponse = $this->getJson(
        '/api/v1/admin/categories/'.$createdCategory->getKey(),
        categoryAdminHeaders($accessToken, 'ar'),
    );

    $showArabicResponse->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('data.slugAr', $originalArabicSlug)
        ->assertJsonPath('data.slugEn', $originalEnglishSlug);

    $updateResponse = $this->patchJson(
        '/api/v1/admin/categories/'.$createdCategory->getKey(),
        [
            'nameAr' => 'خدمات المنزل المحدثة',
            'nameEn' => 'Updated Home Services',
        ],
        categoryAdminHeaders($accessToken, 'en'),
    );

    $updateResponse->assertOk()
        ->assertJsonPath('data.nameAr', 'خدمات المنزل المحدثة')
        ->assertJsonPath('data.nameEn', 'Updated Home Services')
        ->assertJsonPath('data.slugAr', $originalArabicSlug)
        ->assertJsonPath('data.slugEn', $originalEnglishSlug);

    $categoryWithChild = Category::factory()->root()->create([
        'slug_ar' => 'تصنيف-أب',
        'slug_en' => 'parent-category',
    ]);

    Category::factory()->subcategory($categoryWithChild)->create([
        'slug_ar' => 'تصنيف-فرعي',
        'slug_en' => 'child-category',
    ]);

    $this->deleteJson(
        '/api/v1/admin/categories/'.$categoryWithChild->getKey(),
        [],
        categoryAdminHeaders($accessToken, 'en'),
    )
        ->assertConflict()
        ->assertJsonPath('code', 'CATEGORY_HAS_SUBCATEGORIES');

    $reorderTargets = Category::factory()->count(3)->root()->create();

    $orderedIds = [
        $reorderTargets[2]->getKey(),
        $reorderTargets[0]->getKey(),
        $reorderTargets[1]->getKey(),
    ];

    $this->patchJson(
        '/api/v1/admin/categories/reorder',
        ['orderedIds' => $orderedIds],
        categoryAdminHeaders($accessToken, 'en'),
    )
        ->assertOk()
        ->assertJsonPath('data', null);

    expect(Category::query()->find($orderedIds[0])?->sort_order)->toBe(0)
        ->and(Category::query()->find($orderedIds[1])?->sort_order)->toBe(1)
        ->and(Category::query()->find($orderedIds[2])?->sort_order)->toBe(2);

    $deletableCategory = Category::factory()->root()->create([
        'slug_ar' => 'تصنيف-قابل-للحذف',
        'slug_en' => 'deletable-category',
    ]);

    $this->deleteJson(
        '/api/v1/admin/categories/'.$deletableCategory->getKey(),
        [],
        categoryAdminHeaders($accessToken, 'en'),
    )
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null);

    expect($deletableCategory->fresh()?->trashed())->toBeTrue();

    $restoreResponse = $this->postJson(
        '/api/v1/admin/categories/'.$deletableCategory->getKey().'/restore',
        [],
        categoryAdminHeaders($accessToken, 'en'),
    );

    $restoreResponse->assertOk()
        ->assertJsonPath('data.id', $deletableCategory->getKey())
        ->assertJsonPath('data.deletedAt', null);
});

it('rejects invalid root category payloads and preserves query allow-lists', function () {
    $accessToken = categoryAdminToken();

    $this->postJson('/api/v1/admin/categories', [
        'nameAr' => 'خدمات',
        'nameEn' => 'Services',
        'descriptionAr' => 'وصف فقط',
    ], categoryAdminHeaders($accessToken, 'en'))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['payload']]);

    $this->getJson(
        '/api/v1/admin/categories?search=wrong&sortBy=name',
        categoryAdminHeaders($accessToken, 'en'),
    )
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['payload']]);
});

it('returns the approved authentication and permission boundaries for root category routes', function () {
    $this->getJson('/api/v1/admin/categories', [
        'Accept-Language' => 'en',
    ])->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $this->seed(RolesAndPermissionsSeeder::class);

    $inactiveAdmin = User::factory()->administrator()->inactive()->create([
        'email' => 'inactive-category-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    Sanctum::actingAs($inactiveAdmin);

    $this->getJson('/api/v1/admin/categories', [
        'Accept-Language' => 'en',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'USER_INACTIVE');

    $forbiddenAdmin = User::factory()->administrator()->create([
        'email' => 'forbidden-category-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    Sanctum::actingAs($forbiddenAdmin);

    $this->getJson('/api/v1/admin/categories', [
        'Accept-Language' => 'en',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
