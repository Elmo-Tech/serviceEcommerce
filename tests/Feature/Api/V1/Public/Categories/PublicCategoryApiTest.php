<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

it('returns localized public categories and subcategories with locale links and visibility inheritance', function () {
    $rootCategory = Category::factory()->root()->create([
        'name_ar' => 'خدمات المنزل',
        'name_en' => 'Home Services',
        'description_ar' => 'وصف عربي',
        'description_en' => 'English description',
        'slug_ar' => 'خدمات-المنزل',
        'slug_en' => 'home-services',
        'sort_order' => 1,
        'is_active' => true,
        'image_disk' => 'public',
        'image_path' => 'categories/images/root.png',
    ]);

    $visibleSubcategory = Category::factory()->subcategory($rootCategory)->create([
        'name_ar' => 'تنظيف',
        'name_en' => 'Cleaning',
        'description_ar' => 'تنظيف المنازل',
        'description_en' => 'House cleaning',
        'slug_ar' => 'تنظيف',
        'slug_en' => 'cleaning',
        'is_active' => true,
        'image_disk' => 'public',
        'image_path' => 'categories/images/subcategory.png',
    ]);

    Category::factory()->subcategory($rootCategory)->inactive()->create([
        'slug_ar' => 'مخفي',
        'slug_en' => 'hidden',
    ]);

    $englishListResponse = $this->getJson('/api/v1/public/categories', [
        'Accept-Language' => 'en',
    ]);

    $englishListResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('meta.locale', 'en')
        ->assertJsonPath('meta.direction', 'ltr')
        ->assertJsonPath('data.0.name', 'Home Services')
        ->assertJsonPath('data.0.slug', 'home-services')
        ->assertJsonPath('data.0.image', Storage::disk('public')->url('categories/images/root.png'))
        ->assertJsonPath('data.0.subcategories.0.name', 'Cleaning')
        ->assertJsonPath('data.0.subcategories.0.image', Storage::disk('public')->url('categories/images/subcategory.png'))
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.isActive');

    $arabicDetailResponse = $this->getJson('/api/v1/public/categories/خدمات-المنزل', [
        'Accept-Language' => 'ar',
    ]);

    $arabicDetailResponse->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('data.name', 'خدمات المنزل')
        ->assertJsonPath('meta.localeLinks.ar', '/api/v1/public/categories/خدمات-المنزل')
        ->assertJsonPath('meta.localeLinks.en', '/api/v1/public/categories/home-services');

    $englishSubcategoryResponse = $this->getJson('/api/v1/public/categories/home-services/subcategories/cleaning', [
        'Accept-Language' => 'en',
    ]);

    $englishSubcategoryResponse->assertOk()
        ->assertJsonPath('data.name', 'Cleaning')
        ->assertJsonPath('meta.localeLinks.ar', '/api/v1/public/categories/خدمات-المنزل/subcategories/تنظيف')
        ->assertJsonPath('meta.localeLinks.en', '/api/v1/public/categories/home-services/subcategories/cleaning');

    $this->getJson('/api/v1/public/categories/خدمات-المنزل', [
        'Accept-Language' => 'en',
    ])
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $rootCategory->update(['is_active' => false]);

    $this->getJson('/api/v1/public/categories/home-services', [
        'Accept-Language' => 'en',
    ])
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    expect($visibleSubcategory->fresh()?->parent_id)->toBe($rootCategory->getKey());
});
