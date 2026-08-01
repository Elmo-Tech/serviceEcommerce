<?php

declare(strict_types=1);

namespace App\Services\Services;

use App\Enums\HttpStatusCode;
use App\Enums\Services\ServiceMediaType;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Models\ServiceMedia;
use App\Models\ServicePricingOption;

class ServiceChildLimitGuard
{
    public function assertSpecificationLimit(Service $service, int $incoming = 1): void
    {
        $this->assertLimit(
            $service->specifications()->whereNull('deleted_at')->count(),
            $incoming,
            30,
            'services.errors.specification_limit_reached',
            'SERVICE_SPECIFICATION_LIMIT_REACHED',
        );
    }

    public function assertOrderFieldLimit(Service $service, int $incoming = 1): void
    {
        $this->assertLimit(
            $service->orderFields()->whereNull('deleted_at')->count(),
            $incoming,
            20,
            'services.errors.order_field_limit_reached',
            'SERVICE_ORDER_FIELD_LIMIT_REACHED',
        );
    }

    public function assertPricingOptionLimit(Service $service, int $incoming = 1): void
    {
        $this->assertLimit(
            $service->pricingOptions()->whereNull('deleted_at')->count(),
            $incoming,
            10,
            'services.errors.pricing_option_limit_reached',
            'SERVICE_PRICING_OPTION_LIMIT_REACHED',
        );
    }

    public function assertPricingOptionValueLimit(ServicePricingOption $option, int $incoming = 1): void
    {
        $this->assertLimit(
            $option->values()->whereNull('deleted_at')->count(),
            $incoming,
            30,
            'services.errors.pricing_option_value_limit_reached',
            'SERVICE_PRICING_OPTION_VALUE_LIMIT_REACHED',
        );
    }

    public function assertImageLimit(Service $service, int $incoming = 1): void
    {
        $this->assertLimit(
            $service->media()->where('type', ServiceMediaType::IMAGE)->count(),
            $incoming,
            10,
            'service_media.errors.image_limit_reached',
            'SERVICE_IMAGE_LIMIT_REACHED',
        );
    }

    public function assertVideoLimit(Service $service, int $incoming = 1): void
    {
        $this->assertLimit(
            $service->media()->where('type', ServiceMediaType::VIDEO)->count(),
            $incoming,
            1,
            'service_media.errors.video_limit_reached',
            'SERVICE_VIDEO_LIMIT_REACHED',
        );
    }

    public function ensureSingleMainImage(Service $service, ?ServiceMedia $ignore = null): void
    {
        $query = $service->media()->where('type', ServiceMediaType::IMAGE)->where('is_main', true);

        if ($ignore instanceof ServiceMedia) {
            $query->whereKeyNot($ignore->getKey());
        }

        if ($query->exists()) {
            throw new ApiBusinessException(
                'service_media.errors.main_image_exists',
                'SERVICE_MAIN_IMAGE_EXISTS',
                HttpStatusCode::CONFLICT,
            );
        }
    }

    private function assertLimit(
        int $currentCount,
        int $incomingCount,
        int $limit,
        string $translationKey,
        string $code,
    ): void {
        if (($currentCount + $incomingCount) > $limit) {
            throw new ApiBusinessException(
                $translationKey,
                $code,
                HttpStatusCode::UNPROCESSABLE_ENTITY,
            );
        }
    }
}
