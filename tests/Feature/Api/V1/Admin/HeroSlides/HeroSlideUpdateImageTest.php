<?php

declare(strict_types=1);

use App\Models\HeroSlide;
use App\Services\HeroSlides\HeroSlideImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedSuperAdminForAuthTests();
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function heroUpdateImageHeaders(): array
{
    return ['Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'), 'Accept-Language' => 'en'];
}

it('preserves an omitted image and commits replacement before removing the old file', function (string $extension) {
    Storage::disk('public')->put('hero-slides/old.png', 'old');
    $slide = HeroSlide::factory()->atPosition(1)->create(['image_path' => 'hero-slides/old.png']);

    $this->patchJson('/api/v1/admin/hero-slides/'.$slide->getKey(), ['titleEn' => 'No image change'], heroUpdateImageHeaders())
        ->assertOk();
    expect($slide->fresh()?->image_path)->toBe('hero-slides/old.png');

    $response = $this->patch('/api/v1/admin/hero-slides/'.$slide->getKey(), [
        'image' => UploadedFile::fake()->image('replacement.'.$extension),
    ], heroUpdateImageHeaders())->assertOk();

    $newPath = (string) $slide->fresh()?->image_path;
    expect($newPath)->not->toBe('hero-slides/old.png')
        ->and(Storage::disk('public')->exists($newPath))->toBeTrue()
        ->and(Storage::disk('public')->exists('hero-slides/old.png'))->toBeFalse()
        ->and($response->json('data.image'))->toBeUrl();
})->with(['jpg', 'png', 'webp']);

it('rejects remove-image and invalid image content while preserving the old path', function (array $payload) {
    Storage::disk('public')->put('hero-slides/old.png', 'old');
    $slide = HeroSlide::factory()->atPosition(1)->create(['image_path' => 'hero-slides/old.png']);

    $this->patch('/api/v1/admin/hero-slides/'.$slide->getKey(), $payload, heroUpdateImageHeaders())
        ->assertUnprocessable();

    expect($slide->fresh()?->image_path)->toBe('hero-slides/old.png')
        ->and(Storage::disk('public')->exists('hero-slides/old.png'))->toBeTrue();
})->with([
    'remove image' => fn () => ['removeImage' => '1'],
    'invalid image' => fn () => ['image' => UploadedFile::fake()->createWithContent('bad.png', 'not image')],
]);

it('cleans the replacement after a failed database mutation', function () {
    $slide = HeroSlide::factory()->atPosition(2)->create(['image_path' => 'hero-slides/old.png']);

    $this->patch('/api/v1/admin/hero-slides/'.$slide->getKey(), [
        'image' => UploadedFile::fake()->image('replacement.png'),
    ], heroUpdateImageHeaders())->assertStatus(500);

    expect(Storage::disk('public')->allFiles('hero-slides'))->toBe([])
        ->and($slide->fresh()?->image_path)->toBe('hero-slides/old.png');
});

it('logs old-file cleanup failure without changing the committed response', function () {
    $slide = HeroSlide::factory()->atPosition(1)->create(['image_path' => 'hero-slides/old.png']);
    $service = Mockery::mock(HeroSlideImageService::class);
    $service->shouldReceive('store')->once()->andReturn(['disk' => 'public', 'path' => 'hero-slides/new.png']);
    $service->shouldReceive('delete')->once()->with('hero-slides/old.png')->andThrow(new RuntimeException('cleanup'));
    $this->app->instance(HeroSlideImageService::class, $service);
    Log::spy();

    $this->patch('/api/v1/admin/hero-slides/'.$slide->getKey(), [
        'image' => UploadedFile::fake()->image('replacement.png'),
    ], heroUpdateImageHeaders())->assertOk();

    expect($slide->fresh()?->image_path)->toBe('hero-slides/new.png');
    Log::shouldHaveReceived('warning')->once()->with(
        'hero_slides.replaced_image_cleanup_failed',
        ['heroSlideId' => $slide->getKey()],
    );
});
