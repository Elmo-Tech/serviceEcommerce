<?php

declare(strict_types=1);

use App\Models\Service;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function serviceMediaAdminHeaders(string $accessToken, string $locale = 'en'): array
{
    return [
        'Authorization' => 'Bearer '.$accessToken,
        'Accept-Language' => $locale,
    ];
}

function serviceMediaAdminToken(array $credentials = []): string
{
    return (string) loginAdminForTests($credentials)->json('data.accessToken');
}

it('uploads, updates, sets main, and deletes service media safely', function () {
    $accessToken = serviceMediaAdminToken();
    $service = Service::factory()->create(['is_active' => false]);

    $uploadResponse = $this->postJson("/api/v1/admin/services/{$service->getKey()}/media", [
        'media' => [
            [
                'file' => UploadedFile::fake()->image('first.jpg'),
                'type' => 0,
                'isMain' => true,
                'altAr' => 'الصورة الأولى',
                'altEn' => 'First image',
            ],
            [
                'file' => UploadedFile::fake()->image('second.jpg'),
                'type' => 0,
                'altAr' => 'الصورة الثانية',
                'altEn' => 'Second image',
            ],
        ],
    ], serviceMediaAdminHeaders($accessToken));

    $uploadResponse->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.isMain', true);

    $firstMediaId = (int) $uploadResponse->json('data.0.id');
    $secondMediaId = (int) $uploadResponse->json('data.1.id');

    $this->patchJson("/api/v1/admin/services/{$service->getKey()}/media/{$secondMediaId}", [
        'altAr' => 'الصورة الثانية المحدثة',
        'altEn' => 'Updated second image',
    ], serviceMediaAdminHeaders($accessToken))
        ->assertOk()
        ->assertJsonPath('data.altEn', 'Updated second image');

    $this->patchJson("/api/v1/admin/services/{$service->getKey()}/media/{$secondMediaId}/set-as-main", [], serviceMediaAdminHeaders($accessToken))
        ->assertOk()
        ->assertJsonPath('data.isMain', true);

    $listResponse = $this->getJson("/api/v1/admin/services/{$service->getKey()}/media", serviceMediaAdminHeaders($accessToken));
    $listResponse->assertOk()
        ->assertJsonCount(2, 'data');

    $this->deleteJson("/api/v1/admin/services/{$service->getKey()}/media/{$secondMediaId}", [], serviceMediaAdminHeaders($accessToken))
        ->assertOk();

    $finalList = $this->getJson("/api/v1/admin/services/{$service->getKey()}/media", serviceMediaAdminHeaders($accessToken));
    $finalList->assertOk()
        ->assertJsonCount(1, 'data');

    expect($finalList->json('data.0.id'))->toBe($firstMediaId);
});

it('accepts string boolean values for media isMain in upload payloads', function () {
    $accessToken = serviceMediaAdminToken();
    $service = Service::factory()->create(['is_active' => false]);

    $response = $this->post("/api/v1/admin/services/{$service->getKey()}/media", [
        'media' => [
            [
                'file' => UploadedFile::fake()->image('first.jpg'),
                'type' => 0,
                'isMain' => 'true',
                'altAr' => 'الصورة الأولى',
                'altEn' => 'First image',
            ],
            [
                'file' => UploadedFile::fake()->image('second.jpg'),
                'type' => 0,
                'isMain' => 'false',
                'altAr' => 'الصورة الثانية',
                'altEn' => 'Second image',
            ],
        ],
    ], serviceMediaAdminHeaders($accessToken));

    $response->assertCreated()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.isMain', true)
        ->assertJsonPath('data.1.isMain', false);
});
