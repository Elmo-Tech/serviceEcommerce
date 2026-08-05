<?php

declare(strict_types=1);

namespace App\Services\Services;

use App\Enums\HttpStatusCode;
use App\Enums\Services\ServicePriceType;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;

class ServiceActivationValidator
{
    public function validate(Service $service): void
    {
        $requiredFields = [
            $service->name_ar,
            $service->name_en,
            $service->short_description_ar,
            $service->short_description_en,
        ];

        foreach ($requiredFields as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new ApiBusinessException(
                    'services.errors.activation_requires_complete_bilingual_content',
                    'SERVICE_ACTIVATION_REQUIRES_COMPLETE_BILINGUAL_CONTENT',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }
        }

        if ($service->base_price <= 0) {
            throw new ApiBusinessException(
                'services.errors.base_price_must_be_positive',
                'SERVICE_BASE_PRICE_MUST_BE_POSITIVE',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }

        if ($service->price_type === ServicePriceType::START_FROM) {
            $service->loadMissing('pricingOptions.values');

            foreach ($service->pricingOptions->whereNull('deleted_at') as $option) {
                $hasActiveValue = $option->values
                    ->where('deleted_at', null)
                    ->where('is_active', true)
                    ->isNotEmpty();

                if (! $hasActiveValue) {
                    throw new ApiBusinessException(
                        'services.errors.start_from_requires_active_values',
                        'SERVICE_START_FROM_REQUIRES_ACTIVE_VALUES',
                        HttpStatusCode::UNPROCESSABLE_ENTITY,
                    );
                }
            }
        }
    }
}
