<?php

declare(strict_types=1);

use App\Models\Category;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
});

function subcategoryAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function subcategoryAdminToken(): string
{
    return (string) loginAdminForTests()->json('data.accessToken');
}

it('manages nested subcategories with scoped routing, localized indexes, restore conflicts, and scoped reorder', function () {
    $accessToken = subcategoryAdminToken();

    $rootCategory = Category::factory()->root()->create([
        'name_ar' => 'خدمات المنزل',
        'name_en' => 'Home Services',
        'slug_ar' => 'خدمات-المنزل',
        'slug_en' => 'home-services',
    ]);

    $otherRoot = Category::factory()->root()->create([
        'slug_ar' => 'تصنيف-آخر',
        'slug_en' => 'other-root',
    ]);

    $createResponse = $this->postJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories',
        [
            'nameAr' => 'تنظيف',
            'nameEn' => 'Cleaning',
            'descriptionAr' => 'خدمات تنظيف',
            'descriptionEn' => 'Cleaning services',
        ],
        subcategoryAdminHeaders($accessToken, 'en'),
    );

    $createResponse->assertCreated()
        ->assertJsonPath('data.category.id', $rootCategory->getKey())
        ->assertJsonPath('data.nameEn', 'Cleaning');

    $subcategory = Category::query()->subcategories()->sole();

    $this->postJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories',
        [
            'nameAr' => 'تنظيف إضافي',
            'nameEn' => 'Extra Cleaning',
            'parentId' => $rootCategory->getKey(),
        ],
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['payload']]);

    $listResponse = $this->getJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories?filter[search]=clean&filter[isActive]=true&filter[trashed]=without&sort=name',
        subcategoryAdminHeaders($accessToken, 'en'),
    );

    $listResponse->assertOk()
        ->assertJsonPath('data.0.name', 'Cleaning')
        ->assertJsonMissingPath('data.0.nameAr')
        ->assertJsonMissingPath('data.0.slugAr');

    $this->getJson(
        '/api/v1/admin/categories/'.$otherRoot->getKey().'/subcategories/'.$subcategory->getKey(),
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertNotFound()
        ->assertJsonPath('code', 'SUBCATEGORY_NOT_FOUND');

    $this->postJson(
        '/api/v1/admin/categories/'.$subcategory->getKey().'/subcategories',
        [
            'nameAr' => 'مستوى ثالث',
            'nameEn' => 'Third Level',
        ],
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertNotFound()
        ->assertJsonPath('code', 'CATEGORY_NOT_FOUND');

    $this->patchJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories/'.$subcategory->getKey(),
        [
            'nameEn' => 'Updated Cleaning',
        ],
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertOk()
        ->assertJsonPath('data.nameEn', 'Updated Cleaning')
        ->assertJsonPath('data.category.id', $rootCategory->getKey());

    $this->deleteJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories/'.$subcategory->getKey(),
        [],
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertOk()
        ->assertJsonPath('data', null);

    $rootCategory->delete();

    $this->postJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories/'.$subcategory->getKey().'/restore',
        [],
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertConflict()
        ->assertJsonPath('code', 'PARENT_CATEGORY_DELETED');

    $rootCategory->restore();

    $this->postJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories/'.$subcategory->getKey().'/restore',
        [],
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertOk()
        ->assertJsonPath('data.deletedAt', null);

    $first = Category::factory()->subcategory($rootCategory)->create();
    $second = Category::factory()->subcategory($rootCategory)->create();
    $outside = Category::factory()->subcategory($otherRoot)->create();

    $this->patchJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories/reorder',
        [
            'orderedIds' => [$second->getKey(), $first->getKey()],
        ],
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertOk()
        ->assertJsonPath('data', null);

    expect($second->fresh()?->sort_order)->toBe(0)
        ->and($first->fresh()?->sort_order)->toBe(1);

    $this->patchJson(
        '/api/v1/admin/categories/'.$rootCategory->getKey().'/subcategories/reorder',
        [
            'orderedIds' => [$first->getKey(), $outside->getKey()],
        ],
        subcategoryAdminHeaders($accessToken, 'en'),
    )
        ->assertNotFound()
        ->assertJsonPath('code', 'SUBCATEGORY_NOT_FOUND');
});
