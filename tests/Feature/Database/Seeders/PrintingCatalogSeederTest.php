<?php

declare(strict_types=1);

use App\Models\Category;
use Database\Seeders\PrintingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Http::fake([
        'commons.wikimedia.org/*' => Http::response(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);
});

it('seeds six printing categories and twelve nested subcategories with working images', function () {
    $this->seed(PrintingCatalogSeeder::class);

    $roots = Category::query()->roots()->ordered()->with('children')->get();
    $subcategories = Category::query()->subcategories()->get();

    expect($roots)->toHaveCount(6)
        ->and($subcategories)->toHaveCount(12)
        ->and($roots->pluck('children')->flatten())->toHaveCount(12)
        ->and(Category::query()->where('is_active', false)->count())->toBe(0)
        ->and(Category::query()->whereNull('image_disk')->count())->toBe(0)
        ->and(Category::query()->whereNull('image_path')->count())->toBe(0);

    foreach (Category::query()->get() as $category) {
        expect($category->image_disk)->toBe('public')
            ->and($category->image_path)->toBeString();

        Storage::disk('public')->assertExists((string) $category->image_path);
    }

    Http::assertSentCount(6);
});

it('is idempotent, restores seeded rows, and reuses existing image files without new downloads', function () {
    $this->seed(PrintingCatalogSeeder::class);

    $category = Category::query()->where('slug_en', 'commercial-printing')->sole();
    $category->delete();

    Http::fake(fn () => throw new RuntimeException('No HTTP request was expected.'));

    $this->seed(PrintingCatalogSeeder::class);

    expect(Category::query()->count())->toBe(18)
        ->and(Category::onlyTrashed()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('categories/seed'))->toHaveCount(18);

    Http::assertNothingSent();
});

it('rejects an invalid external image without persisting catalogue rows or files', function () {
    Storage::disk('public')->deleteDirectory('categories/seed');
    Http::swap(new Factory);
    Http::fake([
        '*Offset_press.jpg*' => Http::response(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
            200,
            ['Content-Type' => 'image/png'],
        ),
        'commons.wikimedia.org/*' => Http::response('not-an-image', 200, ['Content-Type' => 'text/plain']),
    ]);

    expect(fn () => $this->seed(PrintingCatalogSeeder::class))
        ->toThrow(RuntimeException::class, 'The external catalogue image source returned an invalid image');

    expect(Category::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('categories/seed'))->toBe([]);
});
