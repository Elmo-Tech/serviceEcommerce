<?php

declare(strict_types=1);

use App\Enums\Services\ServiceMediaType;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\ServiceSlugReservation;
use Database\Seeders\PrintingCatalogSeeder;
use Database\Seeders\PrintingServicesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    fakePrintingSeederImages();

    $this->seed(PrintingCatalogSeeder::class);

    fakePrintingSeederImages();
});

it('seeds fifteen printing services under the existing hierarchy with main images', function () {
    $this->seed(PrintingServicesSeeder::class);

    $services = Service::query()
        ->with(['category', 'subcategory.parent', 'mainImage', 'slugReservations'])
        ->get();

    expect($services)->toHaveCount(15)
        ->and(Service::query()->whereNull('category_id')->count())->toBe(0)
        ->and(Service::query()->whereNull('subcategory_id')->count())->toBe(0)
        ->and(Service::query()->where('is_active', false)->count())->toBe(0)
        ->and(Service::query()->where('is_available', false)->count())->toBe(0)
        ->and(ServiceMedia::query()->where('type', ServiceMediaType::IMAGE)->count())->toBe(15)
        ->and(ServiceMedia::query()->where('is_main', true)->count())->toBe(15)
        ->and(ServiceSlugReservation::query()->count())->toBe(30);

    foreach ($services as $service) {
        expect($service->category)->not->toBeNull()
            ->and($service->subcategory)->not->toBeNull()
            ->and($service->subcategory?->parent_id)->toBe($service->category_id)
            ->and($service->mainImage)->not->toBeNull()
            ->and($service->mainImage?->disk)->toBe('public')
            ->and($service->mainImage?->path)->toBeString()
            ->and($service->slugReservations)->toHaveCount(2);

        Storage::disk('public')->assertExists((string) $service->mainImage?->path);
    }

    Http::assertSentCount(15);
});

it('is idempotent, restores seeded services, and preserves a manually selected main image', function () {
    $this->seed(PrintingServicesSeeder::class);

    $service = Service::query()->where('slug_en', 'premium-business-cards')->sole();
    $seededImage = $service->mainImage()->sole();
    Storage::disk('public')->delete($seededImage->path);
    $seededImage->delete();
    Storage::disk('public')->put(
        'services/manual/premium-business-cards.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    );
    $manualImage = $service->media()->create([
        'type' => ServiceMediaType::IMAGE,
        'disk' => 'public',
        'path' => 'services/manual/premium-business-cards.png',
        'stored_name' => 'premium-business-cards.png',
        'original_name' => 'premium-business-cards.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'size_bytes' => 100,
        'alt_text_ar' => 'صورة مخصصة',
        'alt_text_en' => 'Custom image',
        'is_main' => true,
    ]);
    $service->delete();

    Http::fake(fn () => throw new RuntimeException('No HTTP request was expected.'));

    $this->seed(PrintingServicesSeeder::class);

    expect(Service::query()->count())->toBe(15)
        ->and(Service::onlyTrashed()->count())->toBe(0)
        ->and(ServiceSlugReservation::query()->count())->toBe(30)
        ->and($service->fresh()?->mainImage?->is($manualImage))->toBeTrue()
        ->and(Storage::disk('public')->allFiles('services/seed'))->toHaveCount(14)
        ->and(Storage::disk('public')->exists('services/manual/premium-business-cards.png'))->toBeTrue();

    Http::assertNothingSent();
});

it('rejects an invalid external image without persisting services or files', function () {
    Storage::disk('public')->deleteDirectory('services/seed');
    Http::swap(new Factory);
    Http::fake([
        'images.pexels.com/*' => Http::response('not-an-image', 200, ['Content-Type' => 'text/plain']),
    ]);

    expect(fn () => $this->seed(PrintingServicesSeeder::class))
        ->toThrow(RuntimeException::class, 'The external service image source returned an invalid image');

    expect(Service::query()->count())->toBe(0)
        ->and(ServiceMedia::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('services/seed'))->toBe([]);
});

function fakePrintingSeederImages(): void
{
    Http::fake([
        'images.pexels.com/*' => Http::response(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
            200,
            ['Content-Type' => 'image/png'],
        ),
        'cdn.pixabay.com/*' => Http::response(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);
}
