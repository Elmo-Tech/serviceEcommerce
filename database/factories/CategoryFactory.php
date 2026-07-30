<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $arabicSeed = 'تصنيف '.fake()->unique()->numberBetween(1000, 9999);
        $englishSeed = 'Category '.fake()->unique()->numberBetween(1000, 9999);

        return [
            'parent_id' => null,
            'name_ar' => $arabicSeed,
            'name_en' => $englishSeed,
            'description_ar' => 'وصف '.$arabicSeed,
            'description_en' => 'Description for '.$englishSeed,
            'slug_ar' => 'تصنيف-'.fake()->unique()->numberBetween(1000, 9999),
            'slug_en' => 'category-'.fake()->unique()->numberBetween(1000, 9999),
            'sort_order' => fake()->numberBetween(0, 200),
            'is_active' => true,
        ];
    }

    public function root(): static
    {
        return $this->state(fn (): array => [
            'parent_id' => null,
        ]);
    }

    public function subcategory(?Category $parent = null): static
    {
        return $this->state(fn (): array => [
            'parent_id' => $parent?->getKey() ?? Category::factory()->root(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (): array => [
            'deleted_at' => now(),
        ]);
    }
}
