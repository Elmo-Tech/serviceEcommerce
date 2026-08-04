<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Services\ServiceMediaType;
use App\Enums\Services\ServicePriceType;
use App\Models\Category;
use App\Models\Service;
use App\Services\Services\ServiceSlugReservationService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrintingServicesSeeder extends Seeder
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
        private readonly ServiceSlugReservationService $slugReservationService,
    ) {}

    public function run(): void
    {
        $services = $this->services();
        $createdPaths = [];

        try {
            $imagePaths = $this->prepareImages($services, $createdPaths);

            DB::transaction(function () use ($services, $imagePaths): void {
                foreach ($services as $serviceData) {
                    $subcategory = Category::query()
                        ->subcategories()
                        ->where('slug_en', $serviceData['subcategory_slug_en'])
                        ->with('parent')
                        ->sole();

                    $category = $subcategory->parent;

                    if (! $category instanceof Category || ! $category->is_active || ! $subcategory->is_active) {
                        throw new RuntimeException(
                            "The seeded service [{$serviceData['slug_en']}] requires an active category hierarchy.",
                        );
                    }

                    $service = Service::withTrashed()->firstOrNew([
                        'slug_en' => $serviceData['slug_en'],
                    ]);

                    $service->fill([
                        'category_id' => $category->getKey(),
                        'subcategory_id' => $subcategory->getKey(),
                        'name_ar' => $serviceData['name_ar'],
                        'name_en' => $serviceData['name_en'],
                        'short_description_ar' => $serviceData['short_description_ar'],
                        'short_description_en' => $serviceData['short_description_en'],
                        'description_ar' => $serviceData['description_ar'],
                        'description_en' => $serviceData['description_en'],
                        'slug_ar' => $serviceData['slug_ar'],
                        'slug_en' => $serviceData['slug_en'],
                        'production_time_ar' => $serviceData['production_time_ar'],
                        'production_time_en' => $serviceData['production_time_en'],
                        'price_type' => $serviceData['price_type'],
                        'base_price' => $serviceData['base_price'],
                        'is_active' => true,
                        'is_available' => true,
                        'seo_title_ar' => null,
                        'seo_title_en' => null,
                        'seo_description_ar' => null,
                        'seo_description_en' => null,
                        'seo_tags_ar' => null,
                        'seo_tags_en' => null,
                    ]);
                    $service->save();

                    if ($service->trashed()) {
                        $service->restore();
                    }

                    $this->slugReservationService->sync(
                        $service,
                        $serviceData['slug_ar'],
                        $serviceData['slug_en'],
                    );

                    if (! $service->mainImage()->exists()) {
                        $this->createMainImage(
                            $service,
                            $imagePaths[$serviceData['slug_en']],
                            $serviceData,
                        );
                    }
                }
            }, 3);
        } catch (Throwable $exception) {
            foreach ($createdPaths as $path) {
                Storage::disk(self::DISK)->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @param  array<int, string>  $createdPaths
     * @return array<string, string>
     */
    private function prepareImages(array $services, array &$createdPaths): array
    {
        $imagePaths = [];
        $downloadCache = [];

        foreach ($services as $serviceData) {
            $slug = $serviceData['slug_en'];

            if ($this->hasExistingMainImage($slug)) {
                continue;
            }

            $existingPath = $this->validExistingImagePath($slug);

            if ($existingPath !== null) {
                $imagePaths[$slug] = $existingPath;

                continue;
            }

            $source = $serviceData['image_source'];
            $download = $downloadCache[$source] ??= $this->downloadImage($source);
            $path = 'services/seed/'.self::IMAGE_SET."/{$slug}.{$download['extension']}";

            if (! Storage::disk(self::DISK)->put($path, $download['contents'], ['visibility' => 'public'])) {
                throw new RuntimeException("Unable to store the seeded service image for [{$slug}].");
            }

            $createdPaths[] = $path;
            $imagePaths[$slug] = $path;
        }

        return $imagePaths;
    }

    private function hasExistingMainImage(string $slug): bool
    {
        $service = Service::withTrashed()
            ->where('slug_en', $slug)
            ->first();

        return $service instanceof Service && $service->mainImage()->exists();
    }

    /**
     * @return array{contents:string,extension:string,mime_type:string}
     */
    private function downloadImage(string $source): array
    {
        $response = Http::accept('image/jpeg,image/png,image/webp')
            ->withUserAgent('ServiceCommercePrintingServicesSeeder/1.0')
            ->timeout(20)
            ->retry(2, 500)
            ->get($source);

        $response->throw();

        return $this->validatedImage($response, $source);
    }

    /**
     * @return array{contents:string,extension:string,mime_type:string}
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
            throw new RuntimeException("The external service image source returned an invalid image: [{$source}].");
        }

        return [
            'contents' => $contents,
            'extension' => self::EXTENSIONS_BY_MIME[(string) $detectedMime],
            'mime_type' => (string) $detectedMime,
        ];
    }

    private function validExistingImagePath(string $slug): ?string
    {
        foreach (self::EXTENSIONS_BY_MIME as $mimeType => $extension) {
            $path = 'services/seed/'.self::IMAGE_SET."/{$slug}.{$extension}";

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

    /**
     * @param  array<string, mixed>  $serviceData
     */
    private function createMainImage(Service $service, string $path, array $serviceData): void
    {
        $contents = Storage::disk(self::DISK)->get($path);
        $imageInfo = @getimagesizefromstring($contents);
        $mimeType = is_array($imageInfo) && isset($imageInfo['mime']) ? strtolower((string) $imageInfo['mime']) : null;
        $extension = $mimeType !== null ? self::EXTENSIONS_BY_MIME[$mimeType] ?? null : null;

        if ($extension === null) {
            throw new RuntimeException("The stored seeded service image is invalid: [{$path}].");
        }

        $service->media()->create([
            'type' => ServiceMediaType::IMAGE,
            'disk' => self::DISK,
            'path' => $path,
            'stored_name' => basename($path),
            'original_name' => $serviceData['slug_en'].'.'.$extension,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => strlen($contents),
            'alt_text_ar' => $serviceData['name_ar'],
            'alt_text_en' => $serviceData['name_en'],
            'is_main' => true,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function services(): array
    {
        $commercialImage = 'https://cdn.pixabay.com/photo/2015/08/06/10/14/cmyk-877604_640.png';
        $largeFormatImage = 'https://cdn.pixabay.com/photo/2025/12/21/22/12/billboard-10028202_640.png';
        $packagingImage = 'https://cdn.pixabay.com/photo/2022/08/30/21/07/moving-boxes-7421938_640.png';
        $textileImage = 'https://cdn.pixabay.com/photo/2013/07/12/15/53/t-shirt-150525_640.png';
        $promotionalImage = 'https://cdn.pixabay.com/photo/2024/10/26/10/53/mug-9150982_640.png';
        $publicationsImage = 'https://cdn.pixabay.com/photo/2015/12/15/00/23/printing-1093509_640.png';

        return [
            $this->service('كروت شخصية فاخرة', 'Premium Business Cards', 'كروت شخصية احترافية بطباعة عالية الجودة وتشطيبات فاخرة.', 'Professional business cards with premium printing and finishes.', 'تصميم وطباعة كروت شخصية على ورق فاخر مع خيارات متعددة للسماكة والتشطيب لتقديم هوية مهنية مميزة.', 'Design and print premium business cards with multiple paper weights and finishing options for a distinctive professional identity.', 'كروت-شخصية-فاخرة', 'premium-business-cards', 'business-cards', 450, 'من 2 إلى 3 أيام عمل', '2 to 3 business days', ServicePriceType::FIXED, $commercialImage),
            $this->service('كروت شخصية سبوت يو في', 'Spot UV Business Cards', 'كروت شخصية بتشطيب سبوت يو في لإبراز الشعار والتفاصيل.', 'Business cards with spot UV finishing that highlights logos and details.', 'طباعة كروت شخصية فاخرة بطبقة سبوت يو في انتقائية تضيف لمعاناً وملمساً مميزاً للعناصر المهمة في التصميم.', 'Premium business card printing with selective spot UV that adds shine and texture to key design elements.', 'كروت-شخصية-سبوت-يو-في', 'spot-uv-business-cards', 'business-cards', 750, 'من 3 إلى 5 أيام عمل', '3 to 5 business days', ServicePriceType::START_FROM, $commercialImage),
            $this->service('بروشور ثلاثي الطي', 'Tri-Fold Brochures', 'بروشورات تسويقية ثلاثية الطي بتصميم عملي وجذاب.', 'Practical and attractive tri-fold marketing brochures.', 'طباعة بروشورات ثلاثية الطي مناسبة لعرض خدمات الشركات والمنتجات والعروض التسويقية بشكل منظم وواضح.', 'Tri-fold brochures designed to present company services, products, and marketing offers in a clear organized format.', 'بروشور-ثلاثي-الطي', 'tri-fold-brochures', 'brochures-and-flyers', 900, 'من 3 إلى 4 أيام عمل', '3 to 4 business days', ServicePriceType::START_FROM, $commercialImage),
            $this->service('فلاير دعائي مقاس A5', 'A5 Promotional Flyers', 'فلايرات دعائية ملونة مقاس A5 للحملات والعروض.', 'Full-color A5 promotional flyers for campaigns and offers.', 'طباعة فلايرات A5 بألوان واضحة على خامات ورقية مناسبة للتوزيع في الفعاليات والحملات الإعلانية.', 'A5 flyers printed in vivid color on paper suited to events, distribution, and advertising campaigns.', 'فلاير-دعائي-a5', 'a5-promotional-flyers', 'brochures-and-flyers', 600, 'من 2 إلى 3 أيام عمل', '2 to 3 business days', ServicePriceType::FIXED, $commercialImage),
            $this->service('بانر فينيل خارجي', 'Outdoor Vinyl Banners', 'بانرات فينيل قوية مناسبة للإعلانات الخارجية.', 'Durable vinyl banners suitable for outdoor advertising.', 'طباعة بانرات فينيل مقاومة للعوامل الجوية بألوان قوية ومقاسات مخصصة للواجهات والفعاليات الخارجية.', 'Weather-resistant vinyl banners with vivid colors and custom sizes for storefronts and outdoor events.', 'بانر-فينيل-خارجي', 'outdoor-vinyl-banners', 'banners-and-roll-ups', 550, 'من 2 إلى 4 أيام عمل', '2 to 4 business days', ServicePriceType::START_FROM, $largeFormatImage),
            $this->service('ستاند رول أب للمعارض', 'Exhibition Roll-Up Stand', 'رول أب محمول للمعارض والمؤتمرات بنظام سهل الاستخدام.', 'Portable roll-up display for exhibitions and conferences.', 'طباعة وتركيب رول أب احترافي شامل الحامل والحقيبة، مناسب للمعارض ونقاط البيع والفعاليات.', 'Professional roll-up printing with stand and carry bag for exhibitions, retail points, and events.', 'ستاند-رول-اب-للمعارض', 'exhibition-roll-up-stand', 'banners-and-roll-ups', 1250, 'من 2 إلى 3 أيام عمل', '2 to 3 business days', ServicePriceType::FIXED, $largeFormatImage),
            $this->service('بوسترات كبيرة عالية الدقة', 'High-Resolution Large Posters', 'بوسترات كبيرة بألوان دقيقة للمساحات الداخلية والخارجية.', 'Large posters with accurate colors for indoor and outdoor spaces.', 'طباعة بوسترات عالية الدقة بمقاسات متنوعة على خامات ملائمة للحملات الإعلانية والديكور والعروض.', 'High-resolution poster printing in multiple sizes and materials for advertising, decoration, and displays.', 'بوسترات-كبيرة-عالية-الدقة', 'high-resolution-large-posters', 'posters-and-signage', 300, 'من يوم إلى يومين عمل', '1 to 2 business days', ServicePriceType::START_FROM, $largeFormatImage),
            $this->service('علب منتجات مخصصة', 'Custom Product Boxes', 'علب مطبوعة حسب المقاس لتغليف وعرض المنتجات.', 'Custom-sized printed boxes for product packaging and display.', 'تصميم وطباعة علب منتجات مخصصة بخامات وأحجام متعددة تحمي المنتج وتعكس هوية العلامة التجارية.', 'Custom product boxes in multiple materials and sizes that protect products and reinforce brand identity.', 'علب-منتجات-مخصصة', 'custom-product-boxes', 'product-boxes', 2500, 'من 7 إلى 10 أيام عمل', '7 to 10 business days', ServicePriceType::START_FROM, $packagingImage),
            $this->service('استيكرات وليبلز قص خاص', 'Die-Cut Labels and Stickers', 'استيكرات وليبلز بأشكال مخصصة للمنتجات والعبوات.', 'Custom-shaped labels and stickers for products and packaging.', 'طباعة استيكرات وليبلز لاصقة بقص حسب التصميم وخيارات متعددة للخامة واللمعة ومقاومة المياه.', 'Die-cut adhesive labels and stickers with material, finish, and water-resistant options.', 'استيكرات-وليبلز-قص-خاص', 'die-cut-labels-and-stickers', 'labels-and-stickers', 700, 'من 3 إلى 5 أيام عمل', '3 to 5 business days', ServicePriceType::START_FROM, $packagingImage),
            $this->service('تيشيرتات مطبوعة حسب الطلب', 'Custom Printed T-Shirts', 'طباعة تصميمات وشعارات مخصصة على تيشيرتات عالية الجودة.', 'Custom designs and logos printed on quality T-shirts.', 'طباعة تيشيرتات للأفراد والفرق والشركات بتقنيات مناسبة للكمية والتصميم مع ثبات جيد للألوان.', 'T-shirt printing for individuals, teams, and companies using techniques suited to quantity and design with durable colors.', 'تيشيرتات-مطبوعة-حسب-الطلب', 'custom-printed-t-shirts', 't-shirt-printing', 280, 'من 3 إلى 5 أيام عمل', '3 to 5 business days', ServicePriceType::START_FROM, $textileImage),
            $this->service('طباعة أقمشة مخصصة', 'Custom Fabric Printing', 'طباعة أنماط وتصميمات مخصصة على أنواع مختلفة من الأقمشة.', 'Custom patterns and designs printed on different fabric types.', 'خدمة طباعة أقمشة للمشروعات والديكور والمنتجات النسيجية مع اختيار التقنية المناسبة لنوع القماش.', 'Fabric printing for projects, decor, and textile products using the technique best suited to each material.', 'طباعة-اقمشة-مخصصة', 'custom-fabric-printing', 'fabric-printing', 500, 'من 5 إلى 7 أيام عمل', '5 to 7 business days', ServicePriceType::START_FROM, $textileImage),
            $this->service('مج بصورة أو شعار', 'Personalized Photo Mugs', 'مج مخصص مطبوع بصورة أو شعار بألوان واضحة.', 'Personalized mug printed with a photo or logo in vivid color.', 'طباعة حرارية عالية الجودة على مجات مناسبة للهدايا الشخصية والدعاية المؤسسية.', 'High-quality sublimation printing on mugs for personal gifts and corporate promotion.', 'مج-بصورة-او-شعار', 'personalized-photo-mugs', 'printed-mugs', 180, 'من يوم إلى يومين عمل', '1 to 2 business days', ServicePriceType::FIXED, $promotionalImage),
            $this->service('طقم هدايا شركات مطبوع', 'Branded Corporate Gift Set', 'طقم هدايا عملي مخصص بشعار وهوية الشركة.', 'A practical gift set customized with company branding.', 'تجهيز أطقم هدايا شركات تضم منتجات مختارة مع طباعة الشعار وتغليف مناسب للمناسبات والعملاء.', 'Corporate gift sets with selected products, branded printing, and presentation packaging for events and clients.', 'طقم-هدايا-شركات-مطبوع', 'branded-corporate-gift-set', 'corporate-gifts', 1500, 'من 7 إلى 10 أيام عمل', '7 to 10 business days', ServicePriceType::START_FROM, $promotionalImage),
            $this->service('طباعة كتب بغلاف ورقي', 'Paperback Book Printing', 'طباعة وتجليد كتب بغلاف ورقي للكميات الصغيرة والكبيرة.', 'Paperback book printing and binding for small and large quantities.', 'إنتاج كتب داخلية ملونة أو أبيض وأسود مع غلاف ورقي وتجليد احترافي وخيارات متعددة للمقاس والورق.', 'Book production in color or black and white with paperback covers, professional binding, and multiple size and paper options.', 'طباعة-كتب-بغلاف-ورقي', 'paperback-book-printing', 'book-printing', 3500, 'من 7 إلى 14 يوم عمل', '7 to 14 business days', ServicePriceType::START_FROM, $publicationsImage),
            $this->service('كتالوجات ومجلات منتجات', 'Product Catalogues and Magazines', 'كتالوجات ومجلات احترافية لعرض المنتجات والمحتوى.', 'Professional catalogues and magazines for products and editorial content.', 'طباعة كتالوجات ومجلات متعددة الصفحات بجودة صور عالية وتشطيبات مناسبة للعروض التجارية والنشر.', 'Multi-page catalogue and magazine printing with high image quality and finishes suited to sales and publishing.', 'كتالوجات-ومجلات-منتجات', 'product-catalogues-and-magazines', 'magazines-and-catalogues', 2800, 'من 7 إلى 12 يوم عمل', '7 to 12 business days', ServicePriceType::START_FROM, $publicationsImage),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function service(
        string $nameAr,
        string $nameEn,
        string $shortDescriptionAr,
        string $shortDescriptionEn,
        string $descriptionAr,
        string $descriptionEn,
        string $slugAr,
        string $slugEn,
        string $subcategorySlugEn,
        int $basePrice,
        string $productionTimeAr,
        string $productionTimeEn,
        ServicePriceType $priceType,
        string $imageSource,
    ): array {
        return [
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'short_description_ar' => $shortDescriptionAr,
            'short_description_en' => $shortDescriptionEn,
            'description_ar' => $descriptionAr,
            'description_en' => $descriptionEn,
            'slug_ar' => $slugAr,
            'slug_en' => $slugEn,
            'subcategory_slug_en' => $subcategorySlugEn,
            'base_price' => $basePrice,
            'production_time_ar' => $productionTimeAr,
            'production_time_en' => $productionTimeEn,
            'price_type' => $priceType,
            'image_source' => $imageSource,
        ];
    }
}
