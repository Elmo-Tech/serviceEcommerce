<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Services\ServicePriceType;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1000, 9999);

        return [
            'category_id' => null,
            'subcategory_id' => null,
            'name_ar' => 'خدمة '.$suffix,
            'name_en' => 'Service '.$suffix,
            'short_description_ar' => 'وصف مختصر للخدمة '.$suffix,
            'short_description_en' => 'Short description for service '.$suffix,
            'description_ar' => 'وصف كامل للخدمة '.$suffix,
            'description_en' => 'Full description for service '.$suffix,
            'slug_ar' => 'خدمة-'.$suffix,
            'slug_en' => 'service-'.$suffix,
            'production_time_ar' => null,
            'production_time_en' => null,
            'price_type' => ServicePriceType::FIXED,
            'base_price' => fake()->randomFloat(2, 100, 5000),
            'is_active' => true,
            'is_available' => true,
            'is_attachment_required' => false,
            'seo_title_ar' => null,
            'seo_title_en' => null,
            'seo_description_ar' => null,
            'seo_description_en' => null,
            'seo_tags_ar' => null,
            'seo_tags_en' => null,
        ];
    }

    public function fixed(): static
    {
        return $this->state(fn (): array => [
            'price_type' => ServicePriceType::FIXED,
        ]);
    }

    public function startFrom(): static
    {
        return $this->state(fn (): array => [
            'price_type' => ServicePriceType::START_FROM,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => [
            'is_available' => false,
        ]);
    }

    public function categorized(?Category $category = null): static
    {
        return $this->state(fn (): array => [
            'category_id' => $category?->getKey() ?? Category::factory()->root(),
            'subcategory_id' => null,
        ]);
    }

    public function underSubcategory(?Category $root = null, ?Category $subcategory = null): static
    {
        return $this->state(function () use ($root, $subcategory): array {
            $resolvedRoot = $root ?? Category::factory()->root()->create();
            $resolvedSubcategory = $subcategory ?? Category::factory()->subcategory($resolvedRoot)->create();

            return [
                'category_id' => $resolvedRoot->getKey(),
                'subcategory_id' => $resolvedSubcategory->getKey(),
            ];
        });
    }

    public function deleted(): static
    {
        return $this->state(fn (): array => [
            'deleted_at' => now(),
        ]);
    }
}
