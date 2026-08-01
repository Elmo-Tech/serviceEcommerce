<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\HttpStatusCode;
use App\Enums\Services\ServicePriceType;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Models\ServicePricingOption;
use App\Models\ServicePricingOptionValue;
use App\Services\Services\ServiceActivationValidator;
use App\Services\Services\ServiceChildLimitGuard;
use App\Services\Services\ServiceLookupService;
use Illuminate\Support\Facades\DB;

class UpdateServicePricingOptionAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceChildLimitGuard $serviceChildLimitGuard,
        private readonly ServiceActivationValidator $serviceActivationValidator,
    ) {}

    public function execute(Service $service, ServicePricingOption $pricingOption, array $payload): ServicePricingOption
    {
        return DB::transaction(function () use ($service, $pricingOption, $payload): ServicePricingOption {
            $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);

            if ($lockedService->price_type === ServicePriceType::FIXED) {
                throw new ApiBusinessException(
                    'services.errors.fixed_price_prohibits_pricing_options',
                    'FIXED_PRICE_PROHIBITS_PRICING_OPTIONS',
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                );
            }

            $option = $lockedService->pricingOptions()
                ->whereNull('deleted_at')
                ->whereKey($pricingOption->getKey())
                ->lockForUpdate()
                ->first();

            if (! $option instanceof ServicePricingOption) {
                throw new ApiBusinessException(
                    'services.errors.pricing_option_not_found',
                    'SERVICE_PRICING_OPTION_NOT_FOUND',
                    HttpStatusCode::NOT_FOUND,
                );
            }

            $option->fill([
                'name_ar' => $payload['nameAr'] ?? $option->name_ar,
                'name_en' => $payload['nameEn'] ?? $option->name_en,
                'input_type' => $payload['inputType'] ?? $option->input_type,
                'is_required' => $payload['isRequired'] ?? $option->is_required,
                'sort_order' => $payload['sortOrder'] ?? $option->sort_order,
            ])->save();

            if (array_key_exists('values', $payload) && is_array($payload['values']) && $payload['values'] !== []) {
                foreach ($payload['values'] as $valuePayload) {
                    $action = (string) ($valuePayload['actionStatus'] ?? '');

                    if ($action === '') {
                        continue;
                    }

                    if ($action === 'create') {
                        $this->serviceChildLimitGuard->assertPricingOptionValueLimit($option);
                        $option->values()->create([
                            'label_ar' => $valuePayload['labelAr'],
                            'label_en' => $valuePayload['labelEn'],
                            'price_adjustment' => $valuePayload['priceAdjustment'],
                            'is_active' => (bool) $valuePayload['isActive'],
                            'sort_order' => (int) ($valuePayload['sortOrder'] ?? 0),
                        ]);

                        continue;
                    }

                    $value = $option->values()
                        ->withTrashed()
                        ->whereKey((int) ($valuePayload['id'] ?? 0))
                        ->lockForUpdate()
                        ->first();

                    if (! $value instanceof ServicePricingOptionValue) {
                        throw new ApiBusinessException(
                            'services.errors.pricing_option_value_not_found',
                            'SERVICE_PRICING_OPTION_VALUE_NOT_FOUND',
                            HttpStatusCode::NOT_FOUND,
                        );
                    }

                    if ($action === 'update') {
                        $value->fill([
                            'label_ar' => $valuePayload['labelAr'] ?? $value->label_ar,
                            'label_en' => $valuePayload['labelEn'] ?? $value->label_en,
                            'price_adjustment' => $valuePayload['priceAdjustment'] ?? $value->price_adjustment,
                            'is_active' => $valuePayload['isActive'] ?? $value->is_active,
                            'sort_order' => $valuePayload['sortOrder'] ?? $value->sort_order,
                        ])->save();
                    }

                    if ($action === 'delete' && ! $value->trashed()) {
                        $value->delete();
                    }
                }
            }

            if ($lockedService->is_active) {
                $this->serviceActivationValidator->validate($lockedService->fresh()->load('pricingOptions.values'));
            }

            return $option->fresh()->load(['values' => fn ($query) => $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id')]);
        });
    }
}
