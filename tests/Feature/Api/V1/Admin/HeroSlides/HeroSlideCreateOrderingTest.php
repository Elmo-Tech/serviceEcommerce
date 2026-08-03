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

function orderedHeroPayload(?string $position = null): array
{
    $payload = [
        'titleAr' => 'عنوان', 'titleEn' => 'Title',
        'descriptionAr' => 'وصف', 'descriptionEn' => 'Description',
        'image' => UploadedFile::fake()->image('hero.png'), 'isActive' => '1',
    ];

    if ($position !== null) {
        $payload['position'] = $position;
    }

    return $payload;
}

function orderedHeroHeaders(): array
{
    return ['Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'), 'Accept-Language' => 'en'];
}

it('appends and inserts at first middle and count-plus-one positions with automatic shifts', function () {
    $headers = orderedHeroHeaders();
    foreach (range(1, 3) as $position) {
        HeroSlide::factory()->atPosition($position)->create(['title_en' => 'Existing '.$position]);
    }

    $middle = $this->post('/api/v1/admin/hero-slides', orderedHeroPayload('2'), $headers)
        ->assertCreated()->json('data.id');
    expect(HeroSlide::query()->ordered()->pluck('position')->all())->toBe([1, 2, 3, 4])
        ->and(HeroSlide::query()->find($middle)?->position)->toBe(2);

    $this->post('/api/v1/admin/hero-slides', orderedHeroPayload('1'), $headers)->assertCreated();
    $this->post('/api/v1/admin/hero-slides', orderedHeroPayload('6'), $headers)->assertCreated();
    $this->post('/api/v1/admin/hero-slides', orderedHeroPayload(), $headers)->assertCreated();

    expect(HeroSlide::query()->ordered()->pluck('position')->all())->toBe(range(1, 7));
});

it('rejects invalid positions and counts inactive slides toward the ten-row limit', function (string $position) {
    $this->post('/api/v1/admin/hero-slides', orderedHeroPayload($position), orderedHeroHeaders())
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
})->with(['0', '-1', '01', '11']);

it('returns 422 at ten rows and allows creation after deletion frees capacity', function () {
    $headers = orderedHeroHeaders();
    foreach (range(1, 10) as $position) {
        HeroSlide::factory()->atPosition($position)->create(['is_active' => false]);
    }

    $this->post('/api/v1/admin/hero-slides', orderedHeroPayload(), $headers)
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');

    $this->deleteJson('/api/v1/admin/hero-slides/'.HeroSlide::query()->where('position', 10)->value('id'), [], $headers)->assertOk();
    $this->post('/api/v1/admin/hero-slides', orderedHeroPayload(), $headers)->assertCreated();

    expect(HeroSlide::query()->count())->toBe(10)
        ->and(HeroSlide::query()->ordered()->pluck('position')->all())->toBe(range(1, 10));
});
