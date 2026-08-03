<?php

declare(strict_types=1);

use App\Models\HeroSlide;
use App\Services\HeroSlides\HeroSlideImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedSuperAdminForAuthTests();
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function heroDeleteHeaders(): array
{
    return ['Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'), 'Accept-Language' => 'en'];
}

it('permanently deletes compacts positions removes the committed file and returns null data', function () {
    foreach (range(1, 3) as $position) {
        Storage::disk('public')->put("hero-slides/{$position}.png", 'image');
        HeroSlide::factory()->atPosition($position)->create(['image_path' => "hero-slides/{$position}.png"]);
    }

    $target = HeroSlide::query()->where('position', 2)->firstOrFail();
    $this->deleteJson('/api/v1/admin/hero-slides/'.$target->getKey(), [], heroDeleteHeaders())
        ->assertOk()->assertJsonPath('data', null);

    expect(HeroSlide::query()->find($target->getKey()))->toBeNull()
        ->and(HeroSlide::query()->ordered()->pluck('position')->all())->toBe([1, 2])
        ->and(Storage::disk('public')->exists('hero-slides/2.png'))->toBeFalse();
});

it('supports deleting the only slide and returns stable missing outcome', function () {
    $slide = HeroSlide::factory()->atPosition(1)->create();
    $this->deleteJson('/api/v1/admin/hero-slides/'.$slide->getKey(), [], heroDeleteHeaders())->assertOk();
    expect(HeroSlide::query()->count())->toBe(0);

    $this->deleteJson('/api/v1/admin/hero-slides/999999', [], heroDeleteHeaders())
        ->assertNotFound()->assertJsonPath('code', 'HERO_SLIDE_NOT_FOUND');
});

it('logs post-commit delete cleanup failure without undoing the deletion', function () {
    $slide = HeroSlide::factory()->atPosition(1)->create(['image_path' => 'hero-slides/old.png']);
    $service = Mockery::mock(HeroSlideImageService::class);
    $service->shouldReceive('delete')->once()->with('hero-slides/old.png')->andThrow(new RuntimeException('cleanup'));
    $this->app->instance(HeroSlideImageService::class, $service);
    Log::spy();

    $this->deleteJson('/api/v1/admin/hero-slides/'.$slide->getKey(), [], heroDeleteHeaders())->assertOk();
    expect(HeroSlide::query()->find($slide->getKey()))->toBeNull();
    Log::shouldHaveReceived('warning')->once()->with(
        'hero_slides.deleted_image_cleanup_failed',
        ['heroSlideId' => $slide->getKey()],
    );
});
