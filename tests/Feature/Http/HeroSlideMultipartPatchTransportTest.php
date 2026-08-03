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

function rawHeroPatchBody(string $boundary, array $fields, ?array $file = null): string
{
    $body = '';

    foreach ($fields as $name => $value) {
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
    }

    if ($file !== null) {
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"image\"; filename=\"{$file['name']}\"\r\nContent-Type: {$file['type']}\r\n\r\n{$file['contents']}\r\n";
    }

    return $body."--{$boundary}--\r\n";
}

it('delivers real raw multipart PATCH scalars and files through the scoped parser', function () {
    $slide = HeroSlide::factory()->create(['position' => 1]);
    $token = (string) loginAdminForTests()->json('data.accessToken');
    $image = UploadedFile::fake()->image('replacement.png');
    $boundary = 'Feature008HttpBoundary';
    $body = rawHeroPatchBody($boundary, ['titleEn' => 'Raw multipart update', 'isActive' => '0'], [
        'name' => 'replacement.png',
        'type' => 'image/png',
        'contents' => (string) file_get_contents($image->getRealPath()),
    ]);

    $response = $this->call('PATCH', '/api/v1/admin/hero-slides/'.$slide->getKey(), [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_ACCEPT_LANGUAGE' => 'en',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
        'CONTENT_LENGTH' => (string) strlen($body),
    ], $body);

    $response->assertOk()
        ->assertJsonPath('data.titleEn', 'Raw multipart update')
        ->assertJsonPath('data.isActive', 0);

    $fresh = $slide->fresh();
    expect($fresh?->title_en)->toBe('Raw multipart update')
        ->and($fresh?->image_path)->not->toBe($slide->image_path)
        ->and(Storage::disk('public')->exists((string) $fresh?->image_path))->toBeTrue();
});

it('rejects malformed raw multipart only after authentication and permission', function () {
    $slide = HeroSlide::factory()->create(['position' => 1]);
    $boundary = 'Feature008Malformed';

    $this->call('PATCH', '/api/v1/admin/hero-slides/'.$slide->getKey(), [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
    ], '--'.$boundary)
        ->assertUnauthorized();
});
