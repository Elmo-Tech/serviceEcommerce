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

function heroAdminHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept-Language' => 'en'];
}

function heroAdminToken(): string
{
    return (string) loginAdminForTests()->json('data.accessToken');
}

it('creates inserts lists filters shows updates moves and deletes slides with gapless positions', function () {
    $headers = heroAdminHeaders(heroAdminToken());

    foreach ([1, 2] as $number) {
        $this->post('/api/v1/admin/hero-slides', [
            'titleAr' => 'عنوان '.$number,
            'titleEn' => 'Title '.$number,
            'descriptionAr' => 'وصف '.$number,
            'descriptionEn' => 'Description '.$number,
            'image' => UploadedFile::fake()->image("hero-{$number}.png"),
            'isActive' => $number === 1 ? '1' : '0',
        ], $headers)->assertCreated()->assertJsonPath('data.position', $number);
    }

    $insert = $this->post('/api/v1/admin/hero-slides', [
        'titleAr' => 'عنوان وسط',
        'titleEn' => 'Middle',
        'descriptionAr' => 'وصف وسط',
        'descriptionEn' => 'Middle description',
        'image' => UploadedFile::fake()->image('middle.webp'),
        'isActive' => '1',
        'position' => '2',
    ], $headers)->assertCreated();

    expect(HeroSlide::query()->orderBy('position')->pluck('position')->all())->toBe([1, 2, 3]);

    $this->getJson('/api/v1/admin/hero-slides?filter[isActive]=1&page=1&perPage=15', $headers)
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);

    $id = (int) $insert->json('data.id');
    $this->getJson('/api/v1/admin/hero-slides/'.$id, $headers)
        ->assertOk()->assertJsonPath('data.titleEn', 'Middle');

    $this->patchJson('/api/v1/admin/hero-slides/'.$id, [
        'titleEn' => 'Moved',
        'isActive' => '0',
        'position' => '1',
    ], $headers)->assertOk()->assertJsonPath('data.position', 1)->assertJsonPath('data.isActive', 0);

    $this->deleteJson('/api/v1/admin/hero-slides/'.$id, [], $headers)
        ->assertOk()->assertJsonPath('data', null);

    expect(HeroSlide::query()->orderBy('position')->pluck('position')->all())->toBe([1, 2]);
});

it('enforces exact payloads maximum count and missing outcomes', function () {
    $headers = heroAdminHeaders(heroAdminToken());

    $this->post('/api/v1/admin/hero-slides', [
        'titleAr' => 'عنوان', 'titleEn' => 'Title',
        'descriptionAr' => 'وصف', 'descriptionEn' => 'Description',
        'image' => UploadedFile::fake()->image('hero.png'),
        'isActive' => 'true',
    ], $headers)->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');

    foreach (range(1, 10) as $position) {
        HeroSlide::factory()->atPosition($position)->create();
    }

    $this->post('/api/v1/admin/hero-slides', [
        'titleAr' => 'عنوان', 'titleEn' => 'Title',
        'descriptionAr' => 'وصف', 'descriptionEn' => 'Description',
        'image' => UploadedFile::fake()->image('hero.png'),
        'isActive' => '1',
    ], $headers)->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');

    $this->getJson('/api/v1/admin/hero-slides/999999', $headers)
        ->assertNotFound()->assertJsonPath('code', 'HERO_SLIDE_NOT_FOUND');
});
