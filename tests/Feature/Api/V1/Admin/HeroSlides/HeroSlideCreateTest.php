<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedSuperAdminForAuthTests();
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function heroCreateHeaders(): array
{
    return [
        'Authorization' => 'Bearer '.loginAdminForTests()->json('data.accessToken'),
        'Accept-Language' => 'en',
        'Accept' => 'application/json',
    ];
}

function validHeroCreatePayload(array $overrides = []): array
{
    return array_merge([
        'titleAr' => '  عنوان عربي  ',
        'titleEn' => '  English title  ',
        'descriptionAr' => '  وصف عربي  ',
        'descriptionEn' => '  English description  ',
        'image' => UploadedFile::fake()->image('hero.png'),
        'isActive' => '1',
    ], $overrides);
}

it('creates a trimmed exact eight-key admin projection and preserves lexical zero', function () {
    $response = $this->post('/api/v1/admin/hero-slides', validHeroCreatePayload(['isActive' => '0']), heroCreateHeaders());

    $response->assertCreated()
        ->assertJsonPath('data.titleAr', 'عنوان عربي')
        ->assertJsonPath('data.titleEn', 'English title')
        ->assertJsonPath('data.isActive', 0)
        ->assertJsonPath('data.position', 1);

    expect(array_keys($response->json('data')))->toBe([
        'id', 'titleAr', 'titleEn', 'descriptionAr', 'descriptionEn', 'image', 'isActive', 'position',
    ]);
});

it('accepts exact text length boundaries', function () {
    $this->post('/api/v1/admin/hero-slides', validHeroCreatePayload([
        'titleAr' => str_repeat('ع', 150),
        'titleEn' => str_repeat('a', 150),
        'descriptionAr' => str_repeat('و', 1000),
        'descriptionEn' => str_repeat('b', 1000),
    ]), heroCreateHeaders())->assertCreated();
});

it('rejects missing fields controls invalid lexical flags and unknown keys', function (array $payload, string $errorKey) {
    $this->post('/api/v1/admin/hero-slides', $payload, heroCreateHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => [$errorKey]]);
})->with([
    'missing title' => fn () => [array_diff_key(validHeroCreatePayload(), ['titleAr' => true]), 'titleAr'],
    'control character' => fn () => [validHeroCreatePayload(['titleEn' => "bad\x01title"]), 'titleEn'],
    'boolean word' => fn () => [validHeroCreatePayload(['isActive' => 'true']), 'isActive'],
    'non canonical position' => fn () => [validHeroCreatePayload(['position' => '01']), 'position'],
    'unknown key' => fn () => [validHeroCreatePayload(['removeImage' => '1']), 'payload'],
]);
