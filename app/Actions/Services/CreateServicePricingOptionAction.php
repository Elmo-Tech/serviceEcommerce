<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\HttpStatusCode;
use App\Enums\Services\ServicePriceType;
use App\Enums\Services\ServicePricingOptionType;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Models\ServicePricingOption;
use App\Services\Services\ServiceActivationValidator;
use App\Services\Services\ServiceChildLimitGuard;
use App\Services\Services\ServiceLookupService;
use Illuminate\Support\Facades\DB;

class CreateServicePricingOptionAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceChildLimitGuard $serviceChildLimitGuard,
        private readonly ServiceActivationValidator $serviceActivationValidator,
    ) {}

    public function execute(Service $service, array $payload): ServicePricingOption
    {
        return DB::transaction(function () use ($service, $payload): ServicePricingOption {
            $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);

            if ($lockedService->price_type === ServicePriceType::FIXED) {
                throw new ApiBusinessException(
                    'services.errors.fixed_price_prohibits_pricing_options',
                    'FIXED_PRICE_PROHIBITS_PRICING_OPTIONS',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }

            $this->serviceChildLimitGuard->assertPricingOptionLimit($lockedService);

            $option = $lockedService->pricingOptions()->create([
                'name_ar' => $payload['nameAr'],
                'name_en' => $payload['nameEn'],
                'option_type' => ServicePricingOptionType::ADD_ON,
                'input_type' => (int) $payload['inputType'],
                'is_required' => (bool) $payload['isRequired'],
                'sort_order' => (int) ($payload['sortOrder'] ?? 0),
            ]);

            foreach (($payload['values'] ?? []) as $valuePayload) {
                $this->serviceChildLimitGuard->assertPricingOptionValueLimit($option);

                $option->values()->create([
                    'label_ar' => $valuePayload['labelAr'],
                    'label_en' => $valuePayload['labelEn'],
                    'price_adjustment' => $valuePayload['priceAdjustment'],
                    'is_active' => (bool) $valuePayload['isActive'],
                    'sort_order' => (int) ($valuePayload['sortOrder'] ?? 0),
                ]);
            }

            $option->load('values');

            if ($lockedService->is_active) {
                $this->serviceActivationValidator->validate($lockedService->fresh()->load('pricingOptions.values'));
            }

            return $option;
        });
    }
}
