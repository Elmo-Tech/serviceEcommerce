<?php

declare(strict_types=1);

use App\Enums\Services\ServiceMediaType;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns localized active public services with filters and detail projection', function () {
    $category = Category::factory()->root()->create([
        'name_ar' => 'خدمات المنزل',
        'name_en' => 'Home Services',
        'slug_ar' => 'خدمات-المنزل',
        'slug_en' => 'home-services',
        'is_active' => true,
    ]);

    $subcategory = Category::factory()->subcategory($category)->create([
        'name_ar' => 'تنظيف',
        'name_en' => 'Cleaning',
        'slug_ar' => 'تنظيف',
        'slug_en' => 'cleaning',
        'is_active' => true,
    ]);

    $service = Service::factory()->underSubcategory($category, $subcategory)->create([
        'name_ar' => 'خدمة التنظيف',
        'name_en' => 'Cleaning Service',
        'slug_ar' => 'خدمة-التنظيف',
        'slug_en' => 'cleaning-service',
        'is_active' => true,
        'is_available' => true,
    ]);

    ServiceMedia::factory()->image(true)->create([
        'service_id' => $service->getKey(),
        'type' => ServiceMediaType::IMAGE,
    ]);

    Service::factory()->create([
        'name_en' => 'Hidden Service',
        'slug_en' => 'hidden-service',
        'is_active' => false,
    ]);

    $listResponse = $this->getJson('/api/v1/public/services?filter[search]=Cleaning&filter[category]=home-services&filter[subcategory]=cleaning&page=1&perPage=12', [
        'Accept-Language' => 'en',
    ]);

    $listResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.locale', 'en')
        ->assertJsonCount(1, 'data');

    $detailResponse = $this->getJson('/api/v1/public/services/cleaning-service', [
        'Accept-Language' => 'en',
    ]);

    $detailResponse->assertOk()
        ->assertJsonPath('data.name', 'Cleaning Service')
        ->assertJsonPath('data.category.slug', 'home-services')
        ->assertJsonPath('data.subcategory.slug', 'cleaning');
});
