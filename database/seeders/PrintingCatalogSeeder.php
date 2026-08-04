<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrintingCatalogSeeder extends Seeder
{
    private const DISK = 'public';

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const IMAGE_SET = 'print-products-v2';

    /**
     * @var array<string, string>
     */
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function run(): void
    {
        $catalogue = $this->catalogue();
        $createdPaths = [];

        try {
            $imagePaths = $this->prepareImages($catalogue, $createdPaths);

            DB::transaction(function () use ($catalogue, $imagePaths): void {
                foreach ($catalogue as $categoryData) {
                    $category = $this->persistCategory(
                        $categoryData,
                        null,
                        $imagePaths[$categoryData['slug_en']],
                    );

                    foreach ($categoryData['children'] as $subcategoryData) {
                        $this->persistCategory(
                            $subcategoryData,
                            $category->getKey(),
                            $imagePaths[$subcategoryData['slug_en']],
                        );
                    }
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
     * @param  array<int, array<string, mixed>>  $catalogue
     * @param  array<int, string>  $createdPaths
     * @return array<string, string>
     */
    private function prepareImages(array $catalogue, array &$createdPaths): array
    {
        $imagePaths = [];
        $downloadCache = [];

        foreach ($catalogue as $categoryData) {
            $records = [$categoryData, ...$categoryData['children']];

            foreach ($records as $record) {
                $slug = $record['slug_en'];
                $existingPath = $this->validExistingImagePath($slug);

                if ($existingPath !== null) {
                    $imagePaths[$slug] = $existingPath;

                    continue;
                }

                $source = $record['image_source'];
                $download = $downloadCache[$source] ??= $this->downloadImage($source);
                $path = 'categories/seed/'.self::IMAGE_SET."/{$slug}.{$download['extension']}";

                if (! Storage::disk(self::DISK)->put($path, $download['contents'], ['visibility' => 'public'])) {
                    throw new RuntimeException("Unable to store the seeded catalogue image for [{$slug}].");
                }

                $createdPaths[] = $path;
                $imagePaths[$slug] = $path;
            }
        }

        return $imagePaths;
    }

    /**
     * @return array{contents:string,extension:string}
     */
    private function downloadImage(string $source): array
    {
        $response = Http::accept('image/jpeg,image/png,image/webp')
            ->withUserAgent('ServiceCommercePrintingCatalogSeeder/1.0')
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
            throw new RuntimeException("The external catalogue image source returned an invalid image: [{$source}].");
        }

        return [
            'contents' => $contents,
            'extension' => self::EXTENSIONS_BY_MIME[(string) $detectedMime],
        ];
    }

    private function validExistingImagePath(string $slug): ?string
    {
        foreach (array_values(self::EXTENSIONS_BY_MIME) as $extension) {
            $path = 'categories/seed/'.self::IMAGE_SET."/{$slug}.{$extension}";

            if (! Storage::disk(self::DISK)->exists($path)) {
                continue;
            }

            $contents = Storage::disk(self::DISK)->get($path);
            $imageInfo = @getimagesizefromstring($contents);
            $detectedMime = is_array($imageInfo) && isset($imageInfo['mime']) ? strtolower((string) $imageInfo['mime']) : null;

            if (strlen($contents) <= self::MAX_IMAGE_BYTES && isset(self::EXTENSIONS_BY_MIME[(string) $detectedMime])) {
                return $path;
            }

            Storage::disk(self::DISK)->delete($path);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistCategory(array $data, ?int $parentId, string $imagePath): Category
    {
        $category = Category::withTrashed()
            ->firstOrNew(['slug_en' => $data['slug_en']]);

        $category->fill([
            'parent_id' => $parentId,
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'description_ar' => $data['description_ar'],
            'description_en' => $data['description_en'],
            'slug_ar' => $data['slug_ar'],
            'slug_en' => $data['slug_en'],
            'sort_order' => $data['sort_order'],
            'is_active' => true,
            'image_disk' => self::DISK,
            'image_path' => $imagePath,
        ]);
        $category->save();

        if ($category->trashed()) {
            $category->restore();
        }

        return $category;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalogue(): array
    {
        $commercialImage = 'https://images.pexels.com/photos/8490095/pexels-photo-8490095.jpeg?auto=compress&cs=tinysrgb&w=900';
        $businessCardsImage = 'https://images.pexels.com/photos/10932011/pexels-photo-10932011.jpeg?auto=compress&cs=tinysrgb&w=900';
        $brochuresImage = 'https://images.pexels.com/photos/5420976/pexels-photo-5420976.jpeg?auto=compress&cs=tinysrgb&w=900';
        $largeFormatImage = 'https://images.pexels.com/photos/14843552/pexels-photo-14843552.jpeg?auto=compress&cs=tinysrgb&w=900';
        $postersImage = 'https://images.pexels.com/photos/12838778/pexels-photo-12838778.jpeg?auto=compress&cs=tinysrgb&w=900';
        $packagingImage = 'https://images.pexels.com/photos/4440800/pexels-photo-4440800.jpeg?auto=compress&cs=tinysrgb&w=900';
        $productBoxesImage = 'https://images.pexels.com/photos/9594430/pexels-photo-9594430.jpeg?auto=compress&cs=tinysrgb&w=900';
        $labelsImage = 'https://images.pexels.com/photos/4440874/pexels-photo-4440874.jpeg?auto=compress&cs=tinysrgb&w=900';
        $printedTShirtImage = 'https://images.pexels.com/photos/19473187/pexels-photo-19473187.jpeg?auto=compress&cs=tinysrgb&w=900';
        $textileImage = $printedTShirtImage;
        $fabricImage = 'https://images.pexels.com/photos/14944356/pexels-photo-14944356.jpeg?auto=compress&cs=tinysrgb&w=900';
        $promotionalImage = 'https://images.pexels.com/photos/18868628/pexels-photo-18868628.jpeg?auto=compress&cs=tinysrgb&w=900';
        $corporateGiftsImage = 'https://images.pexels.com/photos/5926234/pexels-photo-5926234.jpeg?auto=compress&cs=tinysrgb&w=900';
        $publicationsImage = 'https://images.pexels.com/photos/17352878/pexels-photo-17352878.jpeg?auto=compress&cs=tinysrgb&w=900';
        $bookPrintingImage = 'https://images.pexels.com/photos/36933802/pexels-photo-36933802.jpeg?auto=compress&cs=tinysrgb&w=900';

        return [
            $this->root('الطباعة التجارية', 'Commercial Printing', 'حلول مطبوعة احترافية للشركات والأنشطة التجارية.', 'Professional printed materials for businesses and organizations.', 'الطباعة-التجارية', 'commercial-printing', 10, $commercialImage, [
                $this->child('كروت شخصية', 'Business Cards', 'تصميم وطباعة كروت شخصية بمقاسات وتشطيبات متعددة.', 'Business card printing with multiple sizes and finishes.', 'كروت-شخصية', 'business-cards', 10, $businessCardsImage),
                $this->child('بروشورات وفلايرز', 'Brochures and Flyers', 'مطبوعات تسويقية للتعريف بالخدمات والعروض.', 'Marketing brochures and flyers for services and offers.', 'بروشورات-وفلايرز', 'brochures-and-flyers', 20, $brochuresImage),
            ]),
            $this->root('الطباعة كبيرة الحجم', 'Large Format Printing', 'طباعة عالية الجودة للمساحات الإعلانية الكبيرة.', 'High-quality printing for large advertising formats.', 'الطباعة-كبيرة-الحجم', 'large-format-printing', 20, $largeFormatImage, [
                $this->child('بانرات ورول أب', 'Banners and Roll Ups', 'بانرات ورول أب للمعارض والحملات الإعلانية.', 'Banners and roll-up displays for exhibitions and campaigns.', 'بانرات-ورول-اب', 'banners-and-roll-ups', 10, $largeFormatImage),
                $this->child('بوسترات ولافتات', 'Posters and Signage', 'بوسترات ولافتات داخلية وخارجية بمقاسات متنوعة.', 'Indoor and outdoor posters and signage in various sizes.', 'بوسترات-ولافتات', 'posters-and-signage', 20, $postersImage),
            ]),
            $this->root('طباعة التغليف والعبوات', 'Packaging Printing', 'حلول طباعة للعبوات والمنتجات والعلامات التجارية.', 'Printed packaging solutions for products and brands.', 'طباعة-التغليف-والعبوات', 'packaging-printing', 30, $packagingImage, [
                $this->child('علب المنتجات', 'Product Boxes', 'علب مطبوعة ومخصصة لحماية وعرض المنتجات.', 'Custom printed boxes for product protection and presentation.', 'علب-المنتجات', 'product-boxes', 10, $productBoxesImage),
                $this->child('ليبلز واستيكرات', 'Labels and Stickers', 'ملصقات وليبلز للعبوات والمنتجات بمقاسات مختلفة.', 'Labels and stickers for packaging and products.', 'ليبلز-واستيكرات', 'labels-and-stickers', 20, $labelsImage),
            ]),
            $this->root('طباعة المنسوجات', 'Textile Printing', 'طباعة ثابتة وعالية الجودة على الملابس والأقمشة.', 'Durable, high-quality printing on garments and fabrics.', 'طباعة-المنسوجات', 'textile-printing', 40, $textileImage, [
                $this->child('طباعة تيشيرتات', 'T-Shirt Printing', 'طباعة شعارات وتصميمات مخصصة على التيشيرتات.', 'Custom logos and designs printed on T-shirts.', 'طباعة-تيشيرتات', 't-shirt-printing', 10, $printedTShirtImage),
                $this->child('طباعة أقمشة', 'Fabric Printing', 'طباعة تصميمات وأنماط مخصصة على أنواع الأقمشة.', 'Custom patterns and designs printed on fabrics.', 'طباعة-اقمشة', 'fabric-printing', 20, $fabricImage),
            ]),
            $this->root('الهدايا الدعائية', 'Promotional Products', 'منتجات دعائية مطبوعة لتعزيز حضور العلامة التجارية.', 'Printed promotional products that strengthen brand presence.', 'الهدايا-الدعائية', 'promotional-products', 50, $promotionalImage, [
                $this->child('طباعة مجات', 'Printed Mugs', 'طباعة صور وشعارات مخصصة على المجات.', 'Custom images and logos printed on mugs.', 'طباعة-مجات', 'printed-mugs', 10, $promotionalImage),
                $this->child('هدايا شركات', 'Corporate Gifts', 'هدايا عملية مخصصة بشعار وهوية الشركة.', 'Practical gifts customized with company branding.', 'هدايا-شركات', 'corporate-gifts', 20, $corporateGiftsImage),
            ]),
            $this->root('الكتب والمطبوعات', 'Books and Publications', 'إنتاج المطبوعات متعددة الصفحات بجودة احترافية.', 'Professional production of multi-page printed publications.', 'الكتب-والمطبوعات', 'books-and-publications', 60, $publicationsImage, [
                $this->child('طباعة كتب', 'Book Printing', 'طباعة وتجليد الكتب بمقاسات وكميات مختلفة.', 'Book printing and binding in multiple sizes and quantities.', 'طباعة-كتب', 'book-printing', 10, $bookPrintingImage),
                $this->child('مجلات وكتالوجات', 'Magazines and Catalogues', 'طباعة مجلات وكتالوجات لعرض المنتجات والمحتوى.', 'Magazine and catalogue printing for products and editorial content.', 'مجلات-وكتالوجات', 'magazines-and-catalogues', 20, $publicationsImage),
            ]),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function root(string $nameAr, string $nameEn, string $descriptionAr, string $descriptionEn, string $slugAr, string $slugEn, int $sortOrder, string $imageSource, array $children): array
    {
        return [
            ...$this->child($nameAr, $nameEn, $descriptionAr, $descriptionEn, $slugAr, $slugEn, $sortOrder, $imageSource),
            'children' => $children,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function child(string $nameAr, string $nameEn, string $descriptionAr, string $descriptionEn, string $slugAr, string $slugEn, int $sortOrder, string $imageSource): array
    {
        return [
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'slug_ar' => $slugAr,
            'slug_en' => $slugEn,
            'sort_order' => $sortOrder,
            'image_source' => $imageSource,
        ];
    }
}
