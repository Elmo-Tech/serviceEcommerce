<?php

declare(strict_types=1);

use App\Models\Faq;
use App\Models\HeroSlide;
use Database\Seeders\PrintingFaqSeeder;
use Database\Seeders\PrintingHeroSlidesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    fakePrintingHeroSeederImages();
});

it('seeds five active ordered printing Hero slides with locally stored images', function () {
    $this->seed(PrintingHeroSlidesSeeder::class);

    $slides = HeroSlide::query()->ordered()->get();

    expect($slides)->toHaveCount(5)
        ->and($slides->pluck('position')->all())->toBe([1, 2, 3, 4, 5])
        ->and($slides->where('is_active', false))->toBeEmpty();

    foreach ($slides as $slide) {
        expect($slide->title_ar)->not->toBeEmpty()
            ->and($slide->title_en)->not->toBeEmpty()
            ->and($slide->description_ar)->not->toBeEmpty()
            ->and($slide->description_en)->not->toBeEmpty()
            ->and($slide->image_path)->toStartWith('hero-slides/seed/pexels-printing-slider-v2/');

        Storage::disk('public')->assertExists($slide->image_path);
    }

    Http::assertSentCount(5);
});

it('keeps Hero slide seeding idempotent and preserves a manually replaced image', function () {
    $this->seed(PrintingHeroSlidesSeeder::class);

    $slide = HeroSlide::query()->where('title_en', 'Complete Printing Solutions for Your Business')->sole();
    $seededPath = $slide->image_path;
    Storage::disk('public')->delete($seededPath);
    Storage::disk('public')->put(
        'hero-slides/manual/custom-homepage.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
    );
    $slide->update(['image_path' => 'hero-slides/manual/custom-homepage.png']);

    Http::fake(fn () => throw new RuntimeException('No HTTP request was expected.'));

    $this->seed(PrintingHeroSlidesSeeder::class);

    expect(HeroSlide::query()->count())->toBe(5)
        ->and(HeroSlide::query()->ordered()->pluck('position')->all())->toBe([1, 2, 3, 4, 5])
        ->and($slide->fresh()?->image_path)->toBe('hero-slides/manual/custom-homepage.png')
        ->and(Storage::disk('public')->exists($seededPath))->toBeFalse();

    Http::assertNothingSent();
});

it('upgrades an older seeder-managed Hero image without downloading an existing current image again', function () {
    $this->seed(PrintingHeroSlidesSeeder::class);

    $slide = HeroSlide::query()->where('title_en', 'Large-Format Printing with Lasting Impact')->sole();
    $currentPath = $slide->image_path;
    $slide->update([
        'image_path' => 'hero-slides/seed/pixabay-printing-v1/large-format-impact.png',
    ]);

    Http::fake(fn () => throw new RuntimeException('No HTTP request was expected.'));

    $this->seed(PrintingHeroSlidesSeeder::class);

    expect($slide->fresh()?->image_path)->toBe($currentPath)
        ->and($currentPath)->toStartWith('hero-slides/seed/pexels-printing-slider-v2/');

    Http::assertNothingSent();
});

it('rejects invalid Hero slide images without persisting slides or files', function () {
    Http::swap(new Factory);
    Http::fake([
        'images.pexels.com/*' => Http::response('not-an-image', 200, ['Content-Type' => 'text/plain']),
    ]);

    expect(fn () => $this->seed(PrintingHeroSlidesSeeder::class))
        ->toThrow(RuntimeException::class, 'The external Hero slide image source returned an invalid image');

    expect(HeroSlide::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('hero-slides/seed'))->toBe([]);
});

it('seeds ten active ordered printing FAQs and remains idempotent', function () {
    $this->seed(PrintingFaqSeeder::class);
    $this->seed(PrintingFaqSeeder::class);

    $faqs = Faq::query()->ordered()->get();

    expect($faqs)->toHaveCount(10)
        ->and($faqs->pluck('position')->all())->toBe(range(1, 10))
        ->and($faqs->where('is_active', false))->toBeEmpty()
        ->and($faqs->pluck('question_ar')->unique())->toHaveCount(10)
        ->and($faqs->pluck('question_en')->unique())->toHaveCount(10);
});

function fakePrintingHeroSeederImages(): void
{
    Http::fake([
        'images.pexels.com/*' => Http::response(
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
            200,
            ['Content-Type' => 'image/png'],
        ),
    ]);
}
