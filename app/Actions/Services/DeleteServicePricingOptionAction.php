<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Models\Service;
use App\Models\ServicePricingOption;
use App\Services\Services\ServiceActivationValidator;
use App\Services\Services\ServiceLookupService;
use Illuminate\Support\Facades\DB;

class DeleteServicePricingOptionAction
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceActivationValidator $serviceActivationValidator,
    ) {}

    public function execute(Service $service, ServicePricingOption $pricingOption): void
    {
        DB::transaction(function () use ($service, $pricingOption): void {
            $lockedService = $this->serviceLookupService->lockOrFail($service->getKey(), true);

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

            foreach ($option->values()->whereNull('deleted_at')->lockForUpdate()->get() as $value) {
                $value->delete();
            }

            $option->delete();

            if ($lockedService->is_active) {
                $this->serviceActivationValidator->validate($lockedService->fresh()->load('pricingOptions.values'));
            }
        });
    }
}
