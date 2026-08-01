<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Services\ServiceMediaType;
use App\Models\Service;
use App\Models\ServiceMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceMedia>
 */
class ServiceMediaFactory extends Factory
{
    protected $model = ServiceMedia::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1000, 9999);

        return [
            'service_id' => Service::factory(),
            'type' => ServiceMediaType::IMAGE,
            'disk' => 'public',
            'path' => 'services/'.$suffix.'.jpg',
            'stored_name' => $suffix.'.jpg',
            'original_name' => 'image-'.$suffix.'.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size_bytes' => fake()->numberBetween(1024, 524288),
            'alt_text_ar' => null,
            'alt_text_en' => null,
            'is_main' => false,
        ];
    }

    public function image(bool $main = false): static
    {
        return $this->state(fn (): array => [
            'type' => ServiceMediaType::IMAGE,
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'path' => 'services/'.fake()->unique()->numberBetween(1000, 9999).'.jpg',
            'stored_name' => fake()->unique()->numberBetween(1000, 9999).'.jpg',
            'original_name' => 'image.jpg',
            'is_main' => $main,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'type' => ServiceMediaType::VIDEO,
            'mime_type' => 'video/mp4',
            'extension' => 'mp4',
            'path' => 'services/'.fake()->unique()->numberBetween(1000, 9999).'.mp4',
            'stored_name' => fake()->unique()->numberBetween(1000, 9999).'.mp4',
            'original_name' => 'video.mp4',
            'is_main' => false,
        ]);
    }
}
