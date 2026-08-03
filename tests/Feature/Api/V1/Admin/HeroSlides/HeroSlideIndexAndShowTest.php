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

function heroReadHeaders(string $locale = 'en'): array
{
    return ['Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'), 'Accept-Language' => $locale];
}

it('paginates in fixed ascending order and filters lexical active zero and one', function () {
    foreach (range(1, 20) as $position) {
        HeroSlide::factory()->atPosition($position)->create(['is_active' => $position % 2 === 0]);
    }

    $response = $this->getJson('/api/v1/admin/hero-slides', heroReadHeaders());
    $response->assertOk()->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.currentPage', 1)->assertJsonPath('meta.perPage', 15)
        ->assertJsonPath('meta.total', 20)->assertJsonPath('data.0.position', 1);

    $this->getJson('/api/v1/admin/hero-slides?filter[isActive]=0&perPage=100&page=1', heroReadHeaders())
        ->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('data.0.isActive', 0);
});

it('rejects unknown repeated empty array-shaped and noncanonical query input', function (string $query) {
    $this->getJson('/api/v1/admin/hero-slides?'.$query, heroReadHeaders())
        ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
})->with([
    'unknown=1',
    'page=1&page=2',
    'perPage=',
    'filter[isActive][]=1',
    'filter[isActive]=true',
    'page=01',
]);

it('shows exact bilingual fields and returns stable missing outcome', function () {
    $slide = HeroSlide::factory()->atPosition(1)->create();
    $response = $this->getJson('/api/v1/admin/hero-slides/'.$slide->getKey(), heroReadHeaders('ar'));

    $response->assertOk()->assertHeader('Content-Language', 'ar');
    expect(array_keys($response->json('data')))->toBe([
        'id', 'titleAr', 'titleEn', 'descriptionAr', 'descriptionEn', 'image', 'isActive', 'position',
    ])->and($response->json('data.image'))->toBeUrl()
        ->and(json_encode($response->json(), JSON_THROW_ON_ERROR))->not->toContain('image_path');

    $this->getJson('/api/v1/admin/hero-slides/999999', heroReadHeaders())
        ->assertNotFound()->assertJsonPath('code', 'HERO_SLIDE_NOT_FOUND');
});
