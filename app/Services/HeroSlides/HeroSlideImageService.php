<?php

declare(strict_types=1);

namespace App\Services\HeroSlides;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class HeroSlideImageService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function disk(): string
    {
        return (string) config('filesystems.default', 'public');
    }

    /**
     * @return array{disk:string,path:string}
     */
    public function store(UploadedFile $file): array
    {
        $extension = $this->validatedExtension($file);
        $disk = $this->disk();
        $path = $file->storeAs(
            'hero-slides',
            Str::uuid()->toString().'.'.$extension,
            $disk,
        );

        if (! is_string($path) || $path === '') {
            throw new ApiBusinessException(
                'hero_slides.image_upload_failed',
                'HERO_SLIDE_IMAGE_UPLOAD_FAILED',
                HttpStatusCode::INTERNAL_SERVER_ERROR,
            );
        }

        if (! Storage::disk($disk)->exists($path)) {
            throw new ApiBusinessException(
                'hero_slides.image_upload_failed',
                'HERO_SLIDE_IMAGE_UPLOAD_FAILED',
                HttpStatusCode::INTERNAL_SERVER_ERROR,
            );
        }

        return ['disk' => $disk, 'path' => $path];
    }

    public function delete(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        if (! Storage::disk($this->disk())->delete($path)) {
            throw new RuntimeException('Hero slide image cleanup failed.');
        }
    }

    public function url(string $path): string
    {
        $url = Storage::disk($this->disk())->url($path);

        return parse_url($url, PHP_URL_HOST) !== null ? $url : url($url);
    }

    private function validatedExtension(UploadedFile $file): string
    {
        if (! $file->isValid() || $file->getSize() === false || $file->getSize() > 5 * 1024 * 1024) {
            $this->throwInvalidImage();
        }

        $clientExtension = strtolower($file->getClientOriginalExtension());

        if (! in_array($clientExtension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $this->throwInvalidImage();
        }

        $path = $file->getRealPath();
        $imageInfo = is_string($path) ? @getimagesize($path) : false;
        $detectedMime = $file->getMimeType();

        if ($imageInfo === false || ! is_string($detectedMime) || ! isset(self::MIME_EXTENSIONS[$detectedMime])) {
            $this->throwInvalidImage();
        }

        $imageMime = $imageInfo['mime'] ?? null;

        if ($imageMime !== $detectedMime) {
            $this->throwInvalidImage();
        }

        if ($this->isAnimated($path, $detectedMime)) {
            $this->throwInvalidImage();
        }

        $expectedExtension = self::MIME_EXTENSIONS[$detectedMime];

        if ($expectedExtension === 'jpg' && ! in_array($clientExtension, ['jpg', 'jpeg'], true)) {
            $this->throwInvalidImage();
        }

        if ($expectedExtension !== 'jpg' && $clientExtension !== $expectedExtension) {
            $this->throwInvalidImage();
        }

        return $expectedExtension;
    }

    private function isAnimated(string $path, string $mime): bool
    {
        $contents = @file_get_contents($path);

        if (! is_string($contents)) {
            return true;
        }

        return match ($mime) {
            'image/png' => str_contains($contents, 'acTL'),
            'image/webp' => str_contains($contents, 'ANIM') || str_contains($contents, 'ANMF'),
            default => false,
        };
    }

    private function throwInvalidImage(): never
    {
        throw new ApiBusinessException(
            'hero_slides.invalid_image',
            'VALIDATION_ERROR',
            HttpStatusCode::UNPROCESSABLE_ENTITY,
            ['image' => [__('hero_slides.invalid_image')]],
        );
    }
}
