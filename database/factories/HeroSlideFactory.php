<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    protected $model = HeroSlide::class;

    public function definition(): array
    {
        return [
            'title_ar' => 'حلول رقمية متكاملة',
            'title_en' => 'Complete Digital Solutions',
            'description_ar' => 'نقدم خدمات تناسب احتياجات مشروعك.',
            'description_en' => 'We provide services tailored to your business.',
            'image_path' => 'hero-slides/'.fake()->uuid().'.webp',
            'is_active' => true,
            'position' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function atPosition(int $position): static
    {
        return $this->state(fn (): array => ['position' => $position]);
    }
}
