<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\Services\ServiceOrderFieldType;
use App\Enums\Services\ServicePricingOptionType;
use App\Models\Service;
use App\Services\Files\ServiceMediaStorageService;
use App\Services\Services\LocalizedServiceSlugService;
use App\Services\Services\ServiceActivationValidator;
use App\Services\Services\ServiceChildLimitGuard;
use App\Services\Services\ServiceClassificationValidator;
use App\Services\Services\ServiceHierarchyLockCoordinator;
use App\Services\Services\ServiceSlugReservationService;
use Illuminate\Support\Facades\DB;

class CreateServiceAction
{
    public function __construct(
        private readonly LocalizedServiceSlugService $localizedServiceSlugService,
        private readonly ServiceSlugReservationService $serviceSlugReservationService,
        private readonly ServiceClassificationValidator $serviceClassificationValidator,
        private readonly ServiceActivationValidator $serviceActivationValidator,
        private readonly ServiceChildLimitGuard $serviceChildLimitGuard,
        private readonly ServiceMediaStorageService $serviceMediaStorageService,
        private readonly ServiceHierarchyLockCoordinator $serviceHierarchyLockCoordinator,
    ) {}

    public function execute(array $payload): Service
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($payload, &$storedFiles): Service {
                $categoryId = isset($payload['categoryId']) ? (int) $payload['categoryId'] : null;
                $subcategoryId = isset($payload['subcategoryId']) ? (int) $payload['subcategoryId'] : null;

                [$category, $subcategory] = $this->serviceClassificationValidator->validate($categoryId, $subcategoryId);

                $lockedRootCategories = $this->serviceHierarchyLockCoordinator->lockRootCategories([$categoryId]);
                $lockedSubcategories = $this->serviceHierarchyLockCoordinator->lockSubcategories([$subcategoryId]);
                [$category, $subcategory] = $this->serviceClassificationValidator->validateLocked(
                    $categoryId,
                    $subcategoryId,
                    $lockedRootCategories,
                    $lockedSubcategories,
                );

                $service = Service::query()->create([
                    'category_id' => $category?->getKey(),
                    'subcategory_id' => $subcategory?->getKey(),
                    'name_ar' => trim((string) $payload['nameAr']),
                    'name_en' => trim((string) $payload['nameEn']),
                    'short_description_ar' => trim((string) $payload['shortDescriptionAr']),
                    'short_description_en' => trim((string) $payload['shortDescriptionEn']),
                    'description_ar' => trim((string) $payload['descriptionAr']),
                    'description_en' => trim((string) $payload['descriptionEn']),
                    'slug_ar' => $this->resolveSlug($payload['slugAr'] ?? null, (string) $payload['nameAr']),
                    'slug_en' => $this->resolveSlug($payload['slugEn'] ?? null, (string) $payload['nameEn']),
                    'production_time_ar' => $payload['productionTimeAr'] ?? null,
                    'production_time_en' => $payload['productionTimeEn'] ?? null,
                    'price_type' => (int) $payload['priceType'],
                    'base_price' => $payload['basePrice'],
                    'is_active' => (bool) ($payload['isActive'] ?? false),
                    'is_available' => (bool) ($payload['isAvailable'] ?? true),
                    'is_attachment_required' => (bool) ($payload['isAttachmentRequired'] ?? false),
                    'seo_title_ar' => $payload['seoTitleAr'] ?? null,
                    'seo_title_en' => $payload['seoTitleEn'] ?? null,
                    'seo_description_ar' => $payload['seoDescriptionAr'] ?? null,
                    'seo_description_en' => $payload['seoDescriptionEn'] ?? null,
                    'seo_tags_ar' => $payload['seoTagsAr'] ?? null,
                    'seo_tags_en' => $payload['seoTagsEn'] ?? null,
                ]);

                $this->serviceSlugReservationService->sync($service, $service->slug_ar, $service->slug_en);

                foreach (($payload['specifications'] ?? []) as $item) {
                    $this->serviceChildLimitGuard->assertSpecificationLimit($service);
                    $service->specifications()->create([
                        'label_ar' => $item['labelAr'],
                        'label_en' => $item['labelEn'],
                        'value_ar' => $item['valueAr'],
                        'value_en' => $item['valueEn'],
                        'sort_order' => (int) ($item['sortOrder'] ?? 0),
                    ]);
                }

                foreach (($payload['orderFields'] ?? []) as $item) {
                    $this->serviceChildLimitGuard->assertOrderFieldLimit($service);
                    $service->orderFields()->create([
                        'label_ar' => $item['labelAr'],
                        'label_en' => $item['labelEn'],
                        'field_type' => ServiceOrderFieldType::TEXT,
                        'is_required' => (bool) $item['isRequired'],
                        'sort_order' => (int) ($item['sortOrder'] ?? 0),
                    ]);
                }

                foreach (($payload['pricingOptions'] ?? []) as $item) {
                    $this->serviceChildLimitGuard->assertPricingOptionLimit($service);
                    $option = $service->pricingOptions()->create([
                        'name_ar' => $item['nameAr'],
                        'name_en' => $item['nameEn'],
                        'option_type' => ServicePricingOptionType::ADD_ON,
                        'input_type' => (int) $item['inputType'],
                        'is_required' => (bool) $item['isRequired'],
                        'sort_order' => (int) ($item['sortOrder'] ?? 0),
                    ]);

                    foreach (($item['values'] ?? []) as $valueItem) {
                        $this->serviceChildLimitGuard->assertPricingOptionValueLimit($option);
                        $option->values()->create([
                            'label_ar' => $valueItem['labelAr'],
                            'label_en' => $valueItem['labelEn'],
                            'price_adjustment' => $valueItem['priceAdjustment'],
                            'is_active' => (bool) $valueItem['isActive'],
                            'sort_order' => (int) ($valueItem['sortOrder'] ?? 0),
                        ]);
                    }
                }

                foreach (($payload['media'] ?? []) as $mediaItem) {
                    $stored = $this->serviceMediaStorageService->storeUploadedFile($mediaItem['file']);
                    $storedFiles[] = $stored;
                    $isImage = (int) $mediaItem['type'] === 0;
                    $isMain = $isImage
                        ? ((bool) ($mediaItem['isMain'] ?? false) || ! $service->media()->where('type', 0)->exists())
                        : false;

                    if ($isImage) {
                        $this->serviceChildLimitGuard->assertImageLimit($service);
                    } else {
                        $this->serviceChildLimitGuard->assertVideoLimit($service);
                    }

                    $service->media()->create([
                        ...$stored,
                        'type' => (int) $mediaItem['type'],
                        'alt_text_ar' => $mediaItem['altAr'] ?? null,
                        'alt_text_en' => $mediaItem['altEn'] ?? null,
                        'is_main' => $isMain,
                    ]);
                }

                if ($service->is_active) {
                    $this->serviceActivationValidator->validate($service->fresh()->load('pricingOptions.values'));
                }

                return $service;
            }, 3);
        } catch (\Throwable $throwable) {
            foreach ($storedFiles as $storedFile) {
                $this->serviceMediaStorageService->deleteStoredFile($storedFile);
            }

            throw $throwable;
        }
    }

    private function resolveSlug(mixed $submittedSlug, string $name): string
    {
        if (is_string($submittedSlug) && trim($submittedSlug) !== '') {
            return $this->localizedServiceSlugService->normalize($submittedSlug);
        }

        return $this->localizedServiceSlugService->generateFromName($name);
    }
}
