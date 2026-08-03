<?php

declare(strict_types=1);

use App\Models\HeroSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedSuperAdminForAuthTests();
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function heroUpdateHeaders(): array
{
    return ['Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'), 'Accept-Language' => 'en'];
}

it('updates only present fields preserves omissions and supports lexical zero', function () {
    $slide = HeroSlide::factory()->atPosition(1)->create();
    $originalArabic = $slide->title_ar;

    $this->patchJson('/api/v1/admin/hero-slides/'.$slide->getKey(), [
        'titleEn' => '  Updated title  ', 'isActive' => '0',
    ], heroUpdateHeaders())->assertOk()
        ->assertJsonPath('data.titleEn', 'Updated title')->assertJsonPath('data.isActive', 0);

    expect($slide->fresh()?->title_ar)->toBe($originalArabic);
});

it('moves upward downward and treats the current position as a no-op', function () {
    $slides = collect(range(1, 4))->map(fn (int $position) => HeroSlide::factory()->atPosition($position)->create());
    $headers = heroUpdateHeaders();

    $this->patchJson('/api/v1/admin/hero-slides/'.$slides[3]->getKey(), ['position' => '1'], $headers)->assertOk();
    expect(HeroSlide::query()->ordered()->pluck('id')->all())->toBe([
        $slides[3]->getKey(), $slides[0]->getKey(), $slides[1]->getKey(), $slides[2]->getKey(),
    ]);

    $this->patchJson('/api/v1/admin/hero-slides/'.$slides[3]->getKey(), ['position' => '4'], $headers)->assertOk();
    $this->patchJson('/api/v1/admin/hero-slides/'.$slides[1]->getKey(), ['position' => '2'], $headers)->assertOk();
    expect(HeroSlide::query()->ordered()->pluck('position')->all())->toBe([1, 2, 3, 4]);
});

it('rejects empty unknown null empty and invalid lexical updates', function (array $payload) {
    $slide = HeroSlide::factory()->atPosition(1)->create();
    $this->patchJson('/api/v1/admin/hero-slides/'.$slide->getKey(), $payload, heroUpdateHeaders())
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
})->with([
    'empty' => fn () => [[]],
    'unknown' => fn () => [['unknown' => 'value']],
    'null text' => fn () => [['titleEn' => null]],
    'empty text' => fn () => [['titleEn' => '   ']],
    'boolean word' => fn () => [['isActive' => 'false']],
    'noncanonical position' => fn () => [['position' => '01']],
    'out of range' => fn () => [['position' => '2']],
]);

it('returns stable not found for updates', function () {
    $this->patchJson('/api/v1/admin/hero-slides/999999', ['titleEn' => 'Missing'], heroUpdateHeaders())
        ->assertNotFound()->assertJsonPath('code', 'HERO_SLIDE_NOT_FOUND');
});
