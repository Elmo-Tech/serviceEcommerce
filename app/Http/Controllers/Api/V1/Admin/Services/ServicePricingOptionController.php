<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Services;

use App\Actions\Services\CreateServicePricingOptionAction;
use App\Actions\Services\DeleteServicePricingOptionAction;
use App\Actions\Services\UpdateServicePricingOptionAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Services\ServicePricingOptionRequest;
use App\Http\Requests\Api\V1\Admin\Services\UpdateServicePricingOptionRequest;
use App\Http\Resources\Api\V1\Admin\Services\ServicePricingOptionResource;
use App\Queries\Services\ServiceComponentsIndexQuery;
use App\Services\Services\ServiceLookupService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServicePricingOptionController extends Controller
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceComponentsIndexQuery $serviceComponentsIndexQuery,
        private readonly CreateServicePricingOptionAction $createServicePricingOptionAction,
        private readonly UpdateServicePricingOptionAction $updateServicePricingOptionAction,
        private readonly DeleteServicePricingOptionAction $deleteServicePricingOptionAction,
    ) {}

    public function index(Request $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('services.retrieved'), ServicePricingOptionResource::collection($this->serviceComponentsIndexQuery->pricingOptions($resolvedService))->resolve($request)),
        );
    }

    public function store(ServicePricingOptionRequest $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $pricingOption = $this->createServicePricingOptionAction->execute($resolvedService, $request->validated());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('services.created'), (new ServicePricingOptionResource($pricingOption->load('values')))->resolve($request), HttpStatusCode::CREATED),
        );
    }

    public function show(Request $request, int|string $service, int|string $pricingOption): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedPricingOption = $resolvedService->pricingOptions()->whereNull('deleted_at')->with(['values' => fn ($query) => $query->whereNull('deleted_at')->orderBy('sort_order')->orderBy('id')])->findOrFail((int) $pricingOption);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('services.retrieved'), (new ServicePricingOptionResource($resolvedPricingOption))->resolve($request)));
    }

    public function update(UpdateServicePricingOptionRequest $request, int|string $service, int|string $pricingOption): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedPricingOption = $resolvedService->pricingOptions()->with(['values' => fn ($query) => $query->withTrashed()])->findOrFail((int) $pricingOption);
        $updatedPricingOption = $this->updateServicePricingOptionAction->execute($resolvedService, $resolvedPricingOption, $request->validated());

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('services.updated'), (new ServicePricingOptionResource($updatedPricingOption))->resolve($request)));
    }

    public function destroy(int|string $service, int|string $pricingOption): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedPricingOption = $resolvedService->pricingOptions()->findOrFail((int) $pricingOption);
        $this->deleteServicePricingOptionAction->execute($resolvedService, $resolvedPricingOption);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('services.deleted'), null));
    }
}
