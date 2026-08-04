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

    private const IMAGE_SET = 'pixabay-printing-v1';

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
            $path = $this->targetPath($key, $slideData['extension']);
            $existingSlide = HeroSlide::query()
                ->where('title_en', $slideData['title_en'])
                ->orWhere('image_path', $path)
                ->first();

            if ($existingSlide instanceof HeroSlide && $existingSlide->image_path !== $path) {
                continue;
            }

            if ($this->isValidExistingImage($path)) {
                $paths[$key] = $path;

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

    private function isValidExistingImage(string $path): bool
    {
        if (! Storage::disk(self::DISK)->exists($path)) {
            return false;
        }

        $contents = Storage::disk(self::DISK)->get($path);
        $imageInfo = @getimagesizefromstring($contents);
        $detectedMime = is_array($imageInfo) && isset($imageInfo['mime']) ? strtolower((string) $imageInfo['mime']) : null;

        if (strlen($contents) <= self::MAX_IMAGE_BYTES && isset(self::EXTENSIONS_BY_MIME[(string) $detectedMime])) {
            return true;
        }

        Storage::disk(self::DISK)->delete($path);

        return false;
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
                'https://cdn.pixabay.com/photo/2015/08/06/10/14/cmyk-877604_640.png',
            ),
            $this->slide(
                'large-format-impact',
                'إعلانات كبيرة بتأثير لا يُنسى',
                'Large-Format Printing with Lasting Impact',
                'بانرات ولافتات ورول أب بجودة عالية ومقاسات تناسب المعارض والواجهات والحملات الخارجية.',
                'High-quality banners, signage, and roll-ups sized for exhibitions, storefronts, and outdoor campaigns.',
                'https://cdn.pixabay.com/photo/2025/12/21/22/12/billboard-10028202_640.png',
            ),
            $this->slide(
                'packaging-that-sells',
                'تغليف يعكس قيمة منتجك',
                'Packaging That Helps Your Product Sell',
                'علب وليبلز واستيكرات مصممة بعناية لحماية المنتج وإبراز هوية علامتك التجارية.',
                'Custom boxes, labels, and stickers designed to protect products and showcase your brand identity.',
                'https://cdn.pixabay.com/photo/2022/08/30/21/07/moving-boxes-7421938_640.png',
            ),
            $this->slide(
                'custom-textile-printing',
                'حوّل تصميمك إلى قطعة مميزة',
                'Turn Your Design into Something Wearable',
                'طباعة تيشيرتات وأقمشة مخصصة للأفراد والفرق والشركات بألوان ثابتة وجودة احترافية.',
                'Custom T-shirt and fabric printing for individuals, teams, and businesses with durable professional color.',
                'https://cdn.pixabay.com/photo/2013/07/12/15/53/t-shirt-150525_640.png',
            ),
            $this->slide(
                'books-and-catalogues',
                'مطبوعات تحكي قصتك باحتراف',
                'Printed Publications That Tell Your Story',
                'كتب ومجلات وكتالوجات متعددة الصفحات بطباعة دقيقة وتجليد وتشطيبات تناسب محتواك.',
                'Books, magazines, and catalogues with precise printing, professional binding, and finishes tailored to your content.',
                'https://cdn.pixabay.com/photo/2015/12/15/00/23/printing-1093509_640.png',
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
            'extension' => 'png',
        ];
    }
}
