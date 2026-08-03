<?php

declare(strict_types=1);

use App\Models\HeroSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedSuperAdminForAuthTests();
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function heroImageCreate(array $overrides = []): array
{
    return array_merge([
        'titleAr' => 'عنوان', 'titleEn' => 'Title',
        'descriptionAr' => 'وصف', 'descriptionEn' => 'Description',
        'image' => UploadedFile::fake()->image('hero.png'), 'isActive' => '1',
    ], $overrides);
}

function heroImageHeaders(): array
{
    return ['Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'), 'Accept-Language' => 'en'];
}

it('accepts each approved static image type and never exposes the raw path', function (string $extension) {
    $response = $this->post('/api/v1/admin/hero-slides', heroImageCreate([
        'image' => UploadedFile::fake()->image('unsafe name.'.$extension),
    ]), heroImageHeaders());

    $response->assertCreated();
    expect($response->json('data.image'))->toBeUrl()->not->toContain('unsafe name')
        ->and(json_encode($response->json(), JSON_THROW_ON_ERROR))->not->toContain('image_path');
})->with(['jpg', 'jpeg', 'png', 'webp']);

it('rejects missing SVG and mismatched image content without leaking files', function (array $payload) {
    $this->post('/api/v1/admin/hero-slides', $payload, heroImageHeaders())
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');

    expect(Storage::disk('public')->allFiles('hero-slides'))->toBe([]);
})->with([
    'missing' => fn () => array_diff_key(heroImageCreate(), ['image' => true]),
    'svg' => fn () => heroImageCreate(['image' => UploadedFile::fake()->createWithContent('hero.svg', '<svg/>')]),
    'mismatch' => fn () => heroImageCreate(['image' => UploadedFile::fake()->createWithContent('hero.png', 'not an image')]),
]);

it('cleans a newly stored file when the database mutation fails at the ten-slide limit', function () {
    foreach (range(1, 10) as $position) {
        HeroSlide::factory()->atPosition($position)->create();
    }

    $this->post('/api/v1/admin/hero-slides', heroImageCreate(), heroImageHeaders())
        ->assertUnprocessable();

    expect(Storage::disk('public')->allFiles('hero-slides'))->toBe([]);
});
