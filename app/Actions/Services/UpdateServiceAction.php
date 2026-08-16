<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Services\Services\LocalizedServiceSlugService;
use App\Services\Services\ServiceActivationValidator;
use App\Services\Services\ServiceClassificationValidator;
use App\Services\Services\ServiceHierarchyLockCoordinator;
use App\Services\Services\ServiceLookupService;
use App\Services\Services\ServiceSlugReservationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UpdateServiceAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly LocalizedServiceSlugService $localizedServiceSlugService,
        private readonly ServiceSlugReservationService $serviceSlugReservationService,
        private readonly ServiceClassificationValidator $serviceClassificationValidator,
        private readonly ServiceActivationValidator $serviceActivationValidator,
        private readonly ServiceHierarchyLockCoordinator $serviceHierarchyLockCoordinator,
    ) {}

    public function execute(Service $service, array $payload): Service
    {
        return DB::transaction(function () use ($service, $payload): Service {
            $snapshot = $this->serviceLookupService->findOrFail($service->getKey(), true);

            $categoryId = array_key_exists('categoryId', $payload)
                ? ($payload['categoryId'] === null ? null : (int) $payload['categoryId'])
                : $snapshot->category_id;

            $subcategoryId = array_key_exists('subcategoryId', $payload)
                ? ($payload['subcategoryId'] === null ? null : (int) $payload['subcategoryId'])
                : $snapshot->subcategory_id;

            if (array_key_exists('categoryId', $payload) && $categoryId !== $snapshot->category_id && ! array_key_exists('subcategoryId', $payload)) {
                $subcategoryId = null;
            }

            $lockedRootCategories = $this->serviceHierarchyLockCoordinator->lockRootCategories([$snapshot->category_id, $categoryId]);
            $lockedSubcategories = $this->serviceHierarchyLockCoordinator->lockSubcategories([$snapshot->subcategory_id, $subcategoryId]);
            $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);

            [$category, $subcategory] = $this->serviceClassificationValidator->validateLocked(
                $categoryId,
                $subcategoryId,
                $lockedRootCategories,
                $lockedSubcategories,
            );

            $lockedService->fill([
                'category_id' => $category?->getKey(),
                'subcategory_id' => $subcategory?->getKey(),
                'name_ar' => array_key_exists('nameAr', $payload) ? trim((string) $payload['nameAr']) : $lockedService->name_ar,
                'name_en' => array_key_exists('nameEn', $payload) ? trim((string) $payload['nameEn']) : $lockedService->name_en,
                'short_description_ar' => array_key_exists('shortDescriptionAr', $payload) ? trim((string) $payload['shortDescriptionAr']) : $lockedService->short_description_ar,
                'short_description_en' => array_key_exists('shortDescriptionEn', $payload) ? trim((string) $payload['shortDescriptionEn']) : $lockedService->short_description_en,
                'description_ar' => array_key_exists('descriptionAr', $payload) ? $this->nullableTrimmedString($payload['descriptionAr']) : $lockedService->description_ar,
                'description_en' => array_key_exists('descriptionEn', $payload) ? $this->nullableTrimmedString($payload['descriptionEn']) : $lockedService->description_en,
                'slug_ar' => array_key_exists('slugAr', $payload) ? $this->localizedServiceSlugService->normalizeArabic((string) $payload['slugAr']) : $lockedService->slug_ar,
                'slug_en' => array_key_exists('slugEn', $payload) ? $this->localizedServiceSlugService->normalizeEnglish((string) $payload['slugEn']) : $lockedService->slug_en,
                'production_time_ar' => $payload['productionTimeAr'] ?? $lockedService->production_time_ar,
                'production_time_en' => $payload['productionTimeEn'] ?? $lockedService->production_time_en,
                'price_type' => $payload['priceType'] ?? $lockedService->price_type,
                'base_price' => $payload['basePrice'] ?? $lockedService->base_price,
                'is_active' => $payload['isActive'] ?? $lockedService->is_active,
                'is_available' => $payload['isAvailable'] ?? $lockedService->is_available,
                'is_attachment_required' => $payload['isAttachmentRequired'] ?? $lockedService->is_attachment_required,
                'seo_title_ar' => array_key_exists('seoTitleAr', $payload) ? $payload['seoTitleAr'] : $lockedService->seo_title_ar,
                'seo_title_en' => array_key_exists('seoTitleEn', $payload) ? $payload['seoTitleEn'] : $lockedService->seo_title_en,
                'seo_description_ar' => array_key_exists('seoDescriptionAr', $payload) ? $payload['seoDescriptionAr'] : $lockedService->seo_description_ar,
                'seo_description_en' => array_key_exists('seoDescriptionEn', $payload) ? $payload['seoDescriptionEn'] : $lockedService->seo_description_en,
                'seo_tags_ar' => array_key_exists('seoTagsAr', $payload) ? $payload['seoTagsAr'] : $lockedService->seo_tags_ar,
                'seo_tags_en' => array_key_exists('seoTagsEn', $payload) ? $payload['seoTagsEn'] : $lockedService->seo_tags_en,
            ]);

            try {
                $lockedService->save();
            } catch (QueryException $exception) {
                $this->throwValidationForUniqueServiceConstraint($exception);
            }

            $this->serviceSlugReservationService->sync($lockedService, $lockedService->slug_ar, $lockedService->slug_en);

            if ($lockedService->is_active) {
                $this->serviceActivationValidator->validate($lockedService);
            }

            return $lockedService;
        }, 3);
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function throwValidationForUniqueServiceConstraint(QueryException $exception): never
    {
        $message = $exception->getMessage();
        $field = match (true) {
            str_contains($message, 'uq_services_name_ar') => 'nameAr',
            str_contains($message, 'uq_services_name_en') => 'nameEn',
            str_contains($message, 'uq_services_slug_ar') => 'slugAr',
            str_contains($message, 'uq_services_slug_en') => 'slugEn',
            default => null,
        };

        if ($field === null) {
            throw $exception;
        }

        throw new ApiBusinessException(
            'validation.invalid_payload',
            'VALIDATION_ERROR',
            HttpStatusCode::UNPROCESSABLE_ENTITY,
            [
                $field => [__('validation.unique', ['attribute' => __('validation.attributes.'.$field)])],
            ],
        );
    }
}
