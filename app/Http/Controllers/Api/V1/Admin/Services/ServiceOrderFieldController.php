<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Services;

use App\Enums\HttpStatusCode;
use App\Enums\Services\ServiceOrderFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Services\ServiceOrderFieldRequest;
use App\Http\Requests\Api\V1\Admin\Services\UpdateServiceOrderFieldRequest;
use App\Http\Resources\Api\V1\Admin\Services\ServiceOrderFieldResource;
use App\Queries\Services\ServiceComponentsIndexQuery;
use App\Services\Services\ServiceChildLimitGuard;
use App\Services\Services\ServiceLookupService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceOrderFieldController extends Controller
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
            ApiResponse::success(__('services.retrieved'), ServiceOrderFieldResource::collection($this->serviceComponentsIndexQuery->orderFields($resolvedService))->resolve($request)),
        );
    }

    public function store(ServiceOrderFieldRequest $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->lockOrFail((int) $service, true);
        $this->serviceChildLimitGuard->assertOrderFieldLimit($resolvedService);

        $orderField = $resolvedService->orderFields()->create([
            'label_ar' => $request->string('labelAr')->toString(),
            'label_en' => $request->string('labelEn')->toString(),
            'field_type' => ServiceOrderFieldType::TEXT,
            'is_required' => $request->boolean('isRequired'),
            'sort_order' => (int) $request->input('sortOrder', 0),
        ]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(__('services.created'), (new ServiceOrderFieldResource($orderField))->resolve($request), HttpStatusCode::CREATED),
        );
    }

    public function show(Request $request, int|string $service, int|string $orderField): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedOrderField = $resolvedService->orderFields()->whereNull('deleted_at')->findOrFail((int) $orderField);

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('services.retrieved'), (new ServiceOrderFieldResource($resolvedOrderField))->resolve($request)));
    }

    public function update(UpdateServiceOrderFieldRequest $request, int|string $service, int|string $orderField): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedOrderField = $resolvedService->orderFields()->whereNull('deleted_at')->findOrFail((int) $orderField);

        $resolvedOrderField->fill([
            'label_ar' => $request->input('labelAr', $resolvedOrderField->label_ar),
            'label_en' => $request->input('labelEn', $resolvedOrderField->label_en),
            'is_required' => $request->input('isRequired', $resolvedOrderField->is_required),
            'sort_order' => $request->input('sortOrder', $resolvedOrderField->sort_order),
        ])->save();

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('services.updated'), (new ServiceOrderFieldResource($resolvedOrderField))->resolve($request)));
    }

    public function destroy(int|string $service, int|string $orderField): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $resolvedOrderField = $resolvedService->orderFields()->whereNull('deleted_at')->findOrFail((int) $orderField);
        $resolvedOrderField->delete();

        return ApiResponse::withAuthenticationHeaders(ApiResponse::success(__('services.deleted'), null));
    }
}
