<?php

declare(strict_types=1);

use App\Exceptions\ApiBusinessException;
use App\Services\HeroSlides\HeroSlideImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function heroSizedImage(string $extension, int $bytes): UploadedFile
{
    $source = UploadedFile::fake()->image('source.'.$extension);
    $contents = (string) file_get_contents($source->getRealPath());
    $path = tempnam(sys_get_temp_dir(), 'hero-image-test-');
    file_put_contents($path, $contents.str_repeat("\0", max(0, $bytes - strlen($contents))));

    return new UploadedFile($path, 'unsafe name ../../hero.'.$extension, null, UPLOAD_ERR_OK, true);
}

it('stores valid static image formats using generated mime-derived names and absolute URLs', function (string $extension, string $expectedExtension) {
    $stored = app(HeroSlideImageService::class)->store(UploadedFile::fake()->image('unsafe name.'.$extension));

    expect($stored['disk'])->toBe('public')
        ->and($stored['path'])->toStartWith('hero-slides/')
        ->and($stored['path'])->toEndWith('.'.$expectedExtension)
        ->and($stored['path'])->not->toContain('unsafe')
        ->and(Storage::disk('public')->exists($stored['path']))->toBeTrue()
        ->and(filter_var(app(HeroSlideImageService::class)->url($stored['path']), FILTER_VALIDATE_URL))->not->toBeFalse();
})->with([
    ['jpg', 'jpg'],
    ['jpeg', 'jpg'],
    ['png', 'png'],
    ['webp', 'webp'],
]);

it('accepts exactly five MiB and rejects one byte more', function () {
    $service = app(HeroSlideImageService::class);
    $stored = $service->store(heroSizedImage('png', 5 * 1024 * 1024));

    expect(Storage::disk('public')->size($stored['path']))->toBe(5 * 1024 * 1024);

    expect(fn () => $service->store(heroSizedImage('png', 5 * 1024 * 1024 + 1)))
        ->toThrow(ApiBusinessException::class, 'VALIDATION_ERROR');
});

it('rejects SVG, mime mismatch, animated PNG and animated WebP content', function (UploadedFile $file) {
    expect(fn () => app(HeroSlideImageService::class)->store($file))
        ->toThrow(ApiBusinessException::class, 'VALIDATION_ERROR');
})->with([
    'SVG' => fn () => UploadedFile::fake()->createWithContent('hero.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
    'mismatch' => function () {
        $source = UploadedFile::fake()->image('actual.png');
        $path = tempnam(sys_get_temp_dir(), 'hero-mismatch-');
        file_put_contents($path, (string) file_get_contents($source->getRealPath()));

        return new UploadedFile($path, 'claimed.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);
    },
    'APNG marker' => function () {
        $source = UploadedFile::fake()->image('animated.png');
        file_put_contents($source->getRealPath(), 'acTL', FILE_APPEND);

        return $source;
    },
    'animated WebP marker' => function () {
        $source = UploadedFile::fake()->image('animated.webp');
        file_put_contents($source->getRealPath(), 'ANIM', FILE_APPEND);

        return $source;
    },
]);

it('inspects delete results instead of silently accepting cleanup failure', function () {
    $disk = Mockery::mock();
    $disk->shouldReceive('delete')->once()->with('hero-slides/missing.png')->andReturnFalse();
    Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

    expect(fn () => app(HeroSlideImageService::class)->delete('hero-slides/missing.png'))
        ->toThrow(RuntimeException::class);
});

it('returns the stable upload failure when the configured disk cannot write', function () {
    $disk = Mockery::mock();
    $disk->shouldReceive('putFileAs')->once()->andReturnFalse();
    Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);

    try {
        app(HeroSlideImageService::class)->store(UploadedFile::fake()->image('hero.png'));
        $this->fail('Expected image upload failure.');
    } catch (ApiBusinessException $exception) {
        expect($exception->machineCode())->toBe('HERO_SLIDE_IMAGE_UPLOAD_FAILED');
    }
});
