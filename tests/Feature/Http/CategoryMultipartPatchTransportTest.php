<?php

declare(strict_types=1);

use App\Models\Category;
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

function rawCategoryPatchBody(string $boundary, array $fields, ?array $file = null): string
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

it('handles real multipart PATCH image files and ignores text image values', function () {
    $category = Category::factory()->root()->create([
        'image_disk' => 'public',
        'image_path' => 'categories/images/original.png',
    ]);
    Storage::disk('public')->put('categories/images/original.png', 'original');

    $token = (string) loginAdminForTests()->json('data.accessToken');
    $textBoundary = 'CategoryTextImageBoundary';
    $textBody = rawCategoryPatchBody($textBoundary, [
        'nameEn' => 'Name changed without image replacement',
        'image' => 'https://frontend.example.test/original.png',
    ]);

    $this->call('PATCH', '/api/v1/admin/categories/'.$category->getKey(), [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_ACCEPT_LANGUAGE' => 'en',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        'CONTENT_TYPE' => 'multipart/form-data; boundary='.$textBoundary,
        'CONTENT_LENGTH' => (string) strlen($textBody),
    ], $textBody)
        ->assertOk()
        ->assertJsonPath('data.image', $category->imageUrl());

    expect($category->fresh()?->image_path)->toBe('categories/images/original.png');

    $image = UploadedFile::fake()->image('replacement.png');
    $fileBoundary = 'CategoryFileImageBoundary';
    $fileBody = rawCategoryPatchBody($fileBoundary, [], [
        'name' => 'replacement.png',
        'type' => 'image/png',
        'contents' => (string) file_get_contents($image->getRealPath()),
    ]);

    $this->call('PATCH', '/api/v1/admin/categories/'.$category->getKey(), [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_ACCEPT_LANGUAGE' => 'en',
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        'CONTENT_TYPE' => 'multipart/form-data; boundary='.$fileBoundary,
        'CONTENT_LENGTH' => (string) strlen($fileBody),
    ], $fileBody)
        ->assertOk()
        ->assertJsonPath('data.image', fn (string $value): bool => str_contains($value, '/storage/categories/images/'));

    $updatedCategory = $category->fresh();

    expect($updatedCategory?->image_path)->not->toBe('categories/images/original.png')
        ->and(Storage::disk('public')->exists((string) $updatedCategory?->image_path))->toBeTrue()
        ->and(Storage::disk('public')->exists('categories/images/original.png'))->toBeFalse();
});
