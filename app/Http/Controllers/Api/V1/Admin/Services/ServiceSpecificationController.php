<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Services;

use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Services\ServiceSpecificationRequest;
use App\Http\Requests\Api\V1\Admin\Services\UpdateServiceSpecificationRequest;
use App\Http\Resources\Api\V1\Admin\Services\ServiceSpecificationResource;
use App\Queries\Services\ServiceComponentsIndexQuery;
use App\Services\Services\ServiceChildLimitGuard;
use App\Services\Services\ServiceLookupService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceSpecificationController extends Controller
{
    public function __construct(
        private readonly ServiceLookupService $serviceLookupService,
        private readonly ServiceChildLimitGuard $serviceChildLimitGuard,
        private readonly ServiceComponentsIndexQuery $serviceComponentsIndexQuery,
    ) {}

    public function index(Request $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('services.retrieved'),
                ServiceSpecificationResource::collection($this->serviceComponentsIndexQuery->specifications($resolvedService))->resolve($request),
            ),
        );
    }

    public function store(ServiceSpecificationRequest $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->lockOrFail((int) $service, true);
        $this->serviceChildLimitGuard->assertSpecificationLimit($resolvedService);

        $specification = $resolvedService->specifications()->create([
            'label_ar' => $request->string('labelAr')->toString(),
            'label_en' => $request->string('labelEn')->toString(),
            'value_ar' => $request->string('valueAr')->toString(),
            'value_en' => $request->string('valueEn')->toString(),
            'sort_order' => (int) $request->input('sortOrder', 0),
        ]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('services.created'),
                (new ServiceSpecificationResource($specification))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function show(Request $request, int|string $service, int|string $specification): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedSpecification = $resolvedService->specifications()->whereNull('deleted_at')->findOrFail((int) $specification);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('services.retrieved'), (new ServiceSpecificationResource($resolvedSpecification))->resolve($request)),
        );
    }

    public function update(UpdateServiceSpecificationRequest $request, int|string $service, int|string $specification): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedSpecification = $resolvedService->specifications()->whereNull('deleted_at')->findOrFail((int) $specification);

        $resolvedSpecification->fill([
            'label_ar' => $request->input('labelAr', $resolvedSpecification->label_ar),
            'label_en' => $request->input('labelEn', $resolvedSpecification->label_en),
            'value_ar' => $request->input('valueAr', $resolvedSpecification->value_ar),
            'value_en' => $request->input('valueEn', $resolvedSpecification->value_en),
            'sort_order' => $request->input('sortOrder', $resolvedSpecification->sort_order),
        ])->save();

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('services.updated'), (new ServiceSpecificationResource($resolvedSpecification))->resolve($request)),
        );
    }

    public function destroy(int|string $service, int|string $specification): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedSpecification = $resolvedService->specifications()->whereNull('deleted_at')->findOrFail((int) $specification);
        $resolvedSpecification->delete();

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('services.deleted'), null));
    }
}
