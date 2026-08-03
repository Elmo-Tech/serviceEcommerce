<?php

declare(strict_types=1);

use App\Models\HeroSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

it('allows unauthenticated access and returns only active slides in ascending display order', function () {
    HeroSlide::factory()->create([
        'title_ar' => 'العنوان الثالث',
        'title_en' => 'Third title',
        'description_ar' => 'الوصف الثالث',
        'description_en' => 'Third description',
        'image_path' => 'hero-slides/third.webp',
        'position' => 3,
    ]);
    HeroSlide::factory()->inactive()->create([
        'title_en' => 'Hidden title',
        'image_path' => 'hero-slides/hidden.webp',
        'position' => 2,
    ]);
    HeroSlide::factory()->create([
        'title_ar' => 'العنوان الأول',
        'title_en' => 'First title',
        'description_ar' => 'الوصف الأول',
        'description_en' => 'First description',
        'image_path' => 'hero-slides/first.webp',
        'position' => 1,
    ]);

    $response = $this->getJson('/api/v1/public/hero-slides', [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Hero slides retrieved successfully.')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'First title')
        ->assertJsonPath('data.1.title', 'Third title');

    expect(array_keys($response->json('data.0')))->toBe(['title', 'description', 'image'])
        ->and(filter_var($response->json('data.0.image'), FILTER_VALIDATE_URL))->not->toBeFalse();
});

it('projects Arabic and English locale variants through the same exact neutral keys', function (string $acceptLanguage, string $locale, string $title, string $description) {
    HeroSlide::factory()->create([
        'title_ar' => 'حلول عربية',
        'title_en' => 'English Solutions',
        'description_ar' => 'وصف عربي',
        'description_en' => 'English description',
        'image_path' => 'hero-slides/localized.webp',
        'position' => 1,
    ]);

    $response = $this->getJson('/api/v1/public/hero-slides', [
        'Accept-Language' => $acceptLanguage,
    ]);

    $response->assertOk()
        ->assertHeader('Content-Language', $locale)
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('data.0.title', $title)
        ->assertJsonPath('data.0.description', $description);

    expect(array_keys($response->json('data.0')))->toBe(['title', 'description', 'image']);
})->with([
    'Arabic locale variant' => ['ar-EG', 'ar', 'حلول عربية', 'وصف عربي'],
    'English locale variant' => ['en-US', 'en', 'English Solutions', 'English description'],
]);

it('returns an unpaginated maximum-ten array without admin fields or raw storage paths', function () {
    foreach (range(1, 10) as $position) {
        HeroSlide::factory()->create([
            'title_en' => "Slide {$position}",
            'image_path' => "hero-slides/slide-{$position}.webp",
            'position' => $position,
        ]);
    }

    $response = $this->getJson('/api/v1/public/hero-slides', [
        'Accept-Language' => 'en-US',
    ]);

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.isActive')
        ->assertJsonMissingPath('data.0.position')
        ->assertJsonMissingPath('data.0.titleAr')
        ->assertJsonMissingPath('data.0.titleEn')
        ->assertJsonMissingPath('data.0.descriptionAr')
        ->assertJsonMissingPath('data.0.descriptionEn')
        ->assertJsonMissingPath('data.0.imagePath')
        ->assertJsonMissingPath('data.0.createdAt')
        ->assertJsonMissingPath('data.0.updatedAt');

    foreach ($response->json('data') as $index => $slide) {
        expect(array_keys($slide))->toBe(['title', 'description', 'image'])
            ->and($slide['title'])->toBe('Slide '.($index + 1))
            ->and($slide['image'])->not->toBe('hero-slides/slide-'.($index + 1).'.webp')
            ->and(filter_var($slide['image'], FILTER_VALIDATE_URL))->not->toBeFalse();
    }
});

it('returns the approved empty success envelope when no active slides exist', function () {
    HeroSlide::factory()->inactive()->create([
        'position' => 1,
    ]);

    $this->getJson('/api/v1/public/hero-slides', [
        'Accept-Language' => 'ar',
    ])->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJson([
            'success' => true,
            'message' => __('hero_slides.listed', [], 'ar'),
            'data' => [],
        ])
        ->assertJsonCount(0, 'data');
});

it('registers no public hero slide mutation route', function (string $method) {
    $this->json($method, '/api/v1/public/hero-slides')
        ->assertStatus(405)
        ->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
})->with(['POST', 'PATCH', 'PUT', 'DELETE']);
