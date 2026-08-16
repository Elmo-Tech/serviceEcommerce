<?php

declare(strict_types=1);

use App\Enums\Services\ServiceMediaType;
use App\Enums\Services\ServiceOrderFieldType;
use App\Enums\Services\ServicePriceType;
use App\Enums\Services\ServicePricingInputType;
use App\Enums\Services\ServicePricingOptionType;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\ServiceOrderField;
use App\Models\ServicePricingOption;
use App\Models\ServicePricingOptionValue;
use App\Models\ServiceSlugReservation;
use App\Models\ServiceSpecification;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the approved service tables and indexes', function () {
    foreach ([
        'services',
        'service_slug_reservations',
        'service_specifications',
        'service_order_fields',
        'service_pricing_options',
        'service_pricing_option_values',
        'service_media',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    foreach ([
        'category_id',
        'subcategory_id',
        'slug_ar',
        'slug_en',
        'price_type',
        'base_price',
        'is_active',
        'is_available',
        'deleted_at',
    ] as $column) {
        expect(Schema::hasColumn('services', $column))->toBeTrue();
    }

    $serviceIndexes = collect(DB::select('SHOW INDEX FROM services'))
        ->pluck('Key_name')
        ->unique()
        ->values()
        ->all();

    expect($serviceIndexes)->toContain(
        'PRIMARY',
        'uq_services_name_ar',
        'uq_services_name_en',
        'uq_services_slug_ar',
        'uq_services_slug_en',
        'idx_services_public_created',
        'idx_services_category_public',
        'idx_services_subcategory_public',
        'idx_services_public_price',
        'idx_services_admin_type_created',
        'idx_services_admin_available_created',
        'idx_services_admin_active_created',
    );

    $slugIndexes = collect(DB::select('SHOW INDEX FROM service_slug_reservations'))
        ->pluck('Key_name')
        ->unique()
        ->values()
        ->all();

    expect($slugIndexes)->toContain(
        'PRIMARY',
        'uq_service_slug_reservations_slug',
        'idx_service_slug_reservations_service',
    );
});

it('supports enum casts, ownership relations, and approved child scopes', function () {
    $root = Category::factory()->root()->create();
    $subcategory = Category::factory()->subcategory($root)->create();

    $service = Service::factory()->underSubcategory($root, $subcategory)->startFrom()->create();
    $reservation = ServiceSlugReservation::factory()->create([
        'service_id' => $service->getKey(),
        'slug' => $service->slug_en,
    ]);
    $specification = ServiceSpecification::factory()->create(['service_id' => $service->getKey(), 'sort_order' => 2]);
    $orderField = ServiceOrderField::factory()->create(['service_id' => $service->getKey(), 'sort_order' => 3]);
    $pricingOption = ServicePricingOption::factory()->create(['service_id' => $service->getKey(), 'sort_order' => 4]);
    $pricingValue = ServicePricingOptionValue::factory()->create([
        'service_pricing_option_id' => $pricingOption->getKey(),
        'sort_order' => 5,
    ]);
    $media = ServiceMedia::factory()->image(true)->create(['service_id' => $service->getKey()]);

    expect($service->fresh()->price_type)->toBe(ServicePriceType::START_FROM)
        ->and($service->category?->is($root))->toBeTrue()
        ->and($service->subcategory?->is($subcategory))->toBeTrue()
        ->and($service->slugReservations->first()?->is($reservation))->toBeTrue()
        ->and($service->specifications()->ordered()->first()?->is($specification))->toBeTrue()
        ->and($service->orderFields()->ordered()->first()?->is($orderField))->toBeTrue()
        ->and($service->pricingOptions()->ordered()->first()?->is($pricingOption))->toBeTrue()
        ->and($service->mainImage?->is($media))->toBeTrue()
        ->and($pricingOption->input_type)->toBe(ServicePricingInputType::SELECT)
        ->and($pricingOption->option_type)->toBe(ServicePricingOptionType::ADD_ON)
        ->and($orderField->field_type)->toBe(ServiceOrderFieldType::TEXT)
        ->and($media->type)->toBe(ServiceMediaType::IMAGE)
        ->and($pricingValue->pricingOption?->is($pricingOption))->toBeTrue();
});

it('keeps slug reservations unique across services', function () {
    $first = Service::factory()->create(['slug_en' => 'global-service-slug', 'slug_ar' => 'خدمة-فريدة']);
    ServiceSlugReservation::factory()->create([
        'service_id' => $first->getKey(),
        'slug' => 'global-service-slug',
    ]);

    $second = Service::factory()->create(['slug_en' => 'other-service-slug', 'slug_ar' => 'خدمة-أخرى']);

    expect(fn () => ServiceSlugReservation::query()->create([
        'service_id' => $second->getKey(),
        'slug' => 'global-service-slug',
    ]))->toThrow(QueryException::class);
});

it('retains child rows for soft-deleted services and excludes soft-deleted child rows from ordered scopes', function () {
    $service = Service::factory()->create();
    $specification = ServiceSpecification::factory()->create(['service_id' => $service->getKey(), 'sort_order' => 1]);
    $deletedSpecification = ServiceSpecification::factory()->create([
        'service_id' => $service->getKey(),
        'sort_order' => 2,
        'deleted_at' => now(),
    ]);
    $orderField = ServiceOrderField::factory()->create(['service_id' => $service->getKey()]);
    $pricingOption = ServicePricingOption::factory()->create(['service_id' => $service->getKey()]);
    $value = ServicePricingOptionValue::factory()->create(['service_pricing_option_id' => $pricingOption->getKey()]);
    $media = ServiceMedia::factory()->create(['service_id' => $service->getKey()]);

    $service->delete();

    expect(ServiceSpecification::withTrashed()->find($specification->getKey()))->not->toBeNull()
        ->and(ServiceOrderField::withTrashed()->find($orderField->getKey()))->not->toBeNull()
        ->and(ServicePricingOption::withTrashed()->find($pricingOption->getKey()))->not->toBeNull()
        ->and(ServicePricingOptionValue::withTrashed()->find($value->getKey()))->not->toBeNull()
        ->and(ServiceMedia::query()->find($media->getKey()))->not->toBeNull()
        ->and($service->specifications()->ordered()->pluck('id')->all())->toBe([$specification->getKey()])
        ->and($service->specifications()->withTrashed()->pluck('id')->all())->toContain($deletedSpecification->getKey());
});
