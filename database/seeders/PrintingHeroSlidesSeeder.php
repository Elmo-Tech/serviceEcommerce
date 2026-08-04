<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HeroSlide;
use App\Services\HeroSlides\HeroSlideOrderingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrintingHeroSlidesSeeder extends Seeder
{
    private const DISK = 'public';

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const IMAGE_SET = 'pexels-printing-slider-v2';

    /**
     * @var array<string, string>
     */
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly HeroSlideOrderingService $orderingService,
    ) {}

    public function run(): void
    {
        $slides = $this->slides();
        $createdPaths = [];

        try {
            $imagePaths = $this->prepareImages($slides, $createdPaths);

            $this->orderingService->mutate(function (Collection $persistedSlides) use ($slides, $imagePaths): void {
                foreach ($slides as $slideData) {
                    $existing = $persistedSlides->first(
                        fn (HeroSlide $slide): bool => $slide->title_en === $slideData['title_en']
                            || $slide->image_path === $this->targetPath($slideData['key'], $slideData['extension']),
                    );

                    if ($existing instanceof HeroSlide) {
                        if (
                            str_starts_with($existing->image_path, 'hero-slides/seed/')
                            && isset($imagePaths[$slideData['key']])
                        ) {
                            $existing->update([
                                'image_path' => $imagePaths[$slideData['key']],
                            ]);
                        }

                        continue;
                    }

                    if ($persistedSlides->count() >= 10) {
                        throw new RuntimeException('The printing Hero slide Seeder cannot exceed the 10-slide limit.');
                    }

                    $slide = HeroSlide::query()->create([
                        'title_ar' => $slideData['title_ar'],
                        'title_en' => $slideData['title_en'],
                        'description_ar' => $slideData['description_ar'],
                        'description_en' => $slideData['description_en'],
                        'image_path' => $imagePaths[$slideData['key']],
                        'is_active' => true,
                        'position' => $persistedSlides->count() + 1,
                    ]);

                    $persistedSlides->push($slide);
                }
            });
        } catch (Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk(self::DISK)->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, string>>  $slides
     * @param  array<int, string>  $createdPaths
     * @return array<string, string>
     */
    private function prepareImages(array $slides, array &$createdPaths): array
    {
        $paths = [];

        foreach ($slides as $slideData) {
            $key = $slideData['key'];
            $existingSlide = HeroSlide::query()
                ->where('title_en', $slideData['title_en'])
                ->first();

            if (
                $existingSlide instanceof HeroSlide
                && ! str_starts_with($existingSlide->image_path, 'hero-slides/seed/')
            ) {
                continue;
            }

            $existingPath = $this->validExistingImagePath($key);

            if ($existingPath !== null) {
                $paths[$key] = $existingPath;

                continue;
            }

            $download = $this->downloadImage($slideData['image_source']);
            $path = $this->targetPath($key, $download['extension']);

            if (! Storage::disk(self::DISK)->put($path, $download['contents'], ['visibility' => 'public'])) {
                throw new RuntimeException("Unable to store the seeded Hero slide image for [{$key}].");
            }

            $createdPaths[] = $path;
            $paths[$key] = $path;
        }

        return $paths;
    }

    /**
     * @return array{contents:string,extension:string}
     */
    private function downloadImage(string $source): array
    {
        $response = Http::accept('image/jpeg,image/png,image/webp')
            ->withUserAgent('ServiceCommercePrintingHeroSlidesSeeder/1.0')
            ->timeout(20)
            ->retry(2, 500)
            ->get($source);

        $response->throw();

        return $this->validatedImage($response, $source);
    }

    /**
     * @return array{contents:string,extension:string}
     */
    private function validatedImage(Response $response, string $source): array
    {
        $contents = $response->body();
        $imageInfo = @getimagesizefromstring($contents);
        $detectedMime = is_array($imageInfo) && isset($imageInfo['mime']) ? strtolower((string) $imageInfo['mime']) : null;

        if (
            $contents === ''
            || strlen($contents) > self::MAX_IMAGE_BYTES
            || ! isset(self::EXTENSIONS_BY_MIME[(string) $detectedMime])
        ) {
            throw new RuntimeException("The external Hero slide image source returned an invalid image: [{$source}].");
        }

        return [
            'contents' => $contents,
            'extension' => self::EXTENSIONS_BY_MIME[(string) $detectedMime],
        ];
    }

    private function validExistingImagePath(string $key): ?string
    {
        foreach (self::EXTENSIONS_BY_MIME as $mimeType => $extension) {
            $path = $this->targetPath($key, $extension);

            if (! Storage::disk(self::DISK)->exists($path)) {
                continue;
            }

            $contents = Storage::disk(self::DISK)->get($path);
            $imageInfo = @getimagesizefromstring($contents);
            $detectedMime = is_array($imageInfo) && isset($imageInfo['mime']) ? strtolower((string) $imageInfo['mime']) : null;

            if (strlen($contents) <= self::MAX_IMAGE_BYTES && $detectedMime === $mimeType) {
                return $path;
            }

            Storage::disk(self::DISK)->delete($path);
        }

        return null;
    }

    private function targetPath(string $key, string $extension): string
    {
        return 'hero-slides/seed/'.self::IMAGE_SET."/{$key}.{$extension}";
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function slides(): array
    {
        return [
            $this->slide(
                'complete-printing-solutions',
                'حلول طباعة متكاملة لأعمالك',
                'Complete Printing Solutions for Your Business',
                'من الفكرة والتصميم إلى الطباعة والتشطيب، نقدم كل ما تحتاجه علامتك التجارية في مكان واحد.',
                'From concept and design to printing and finishing, everything your brand needs is available in one place.',
                'https://images.pexels.com/photos/8490095/pexels-photo-8490095.jpeg?auto=compress&cs=tinysrgb&w=1600&h=600&fit=crop',
            ),
            $this->slide(
                'large-format-impact',
                'إعلانات كبيرة بتأثير لا يُنسى',
                'Large-Format Printing with Lasting Impact',
                'بانرات ولافتات ورول أب بجودة عالية ومقاسات تناسب المعارض والواجهات والحملات الخارجية.',
                'High-quality banners, signage, and roll-ups sized for exhibitions, storefronts, and outdoor campaigns.',
                'https://images.pexels.com/photos/36519146/pexels-photo-36519146.jpeg?auto=compress&cs=tinysrgb&w=1600&h=600&fit=crop',
            ),
            $this->slide(
                'packaging-that-sells',
                'تغليف يعكس قيمة منتجك',
                'Packaging That Helps Your Product Sell',
                'علب وليبلز واستيكرات مصممة بعناية لحماية المنتج وإبراز هوية علامتك التجارية.',
                'Custom boxes, labels, and stickers designed to protect products and showcase your brand identity.',
                'https://images.pexels.com/photos/9594430/pexels-photo-9594430.jpeg?auto=compress&cs=tinysrgb&w=1600&h=600&fit=crop',
            ),
            $this->slide(
                'custom-textile-printing',
                'حوّل تصميمك إلى قطعة مميزة',
                'Turn Your Design into Something Wearable',
                'طباعة تيشيرتات وأقمشة مخصصة للأفراد والفرق والشركات بألوان ثابتة وجودة احترافية.',
                'Custom T-shirt and fabric printing for individuals, teams, and businesses with durable professional color.',
                'https://images.pexels.com/photos/19473187/pexels-photo-19473187.jpeg?auto=compress&cs=tinysrgb&w=1600&h=600&fit=crop',
            ),
            $this->slide(
                'books-and-catalogues',
                'مطبوعات تحكي قصتك باحتراف',
                'Printed Publications That Tell Your Story',
                'كتب ومجلات وكتالوجات متعددة الصفحات بطباعة دقيقة وتجليد وتشطيبات تناسب محتواك.',
                'Books, magazines, and catalogues with precise printing, professional binding, and finishes tailored to your content.',
                'https://images.pexels.com/photos/17352878/pexels-photo-17352878.jpeg?auto=compress&cs=tinysrgb&w=1600&h=600&fit=crop',
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function slide(
        string $key,
        string $titleAr,
        string $titleEn,
        string $descriptionAr,
        string $descriptionEn,
        string $imageSource,
    ): array {
        return [
            'key' => $key,
            'title_ar' => $titleAr,
            'title_en' => $titleEn,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'image_source' => $imageSource,
            'extension' => 'jpg',
        ];
    }
}
