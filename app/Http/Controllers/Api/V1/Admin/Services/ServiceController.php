<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Services;

use App\Actions\Services\CreateServiceAction;
use App\Actions\Services\DeleteServiceAction;
use App\Actions\Services\RestoreServiceAction;
use App\Actions\Services\UpdateServiceAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Services\ListServicesRequest;
use App\Http\Requests\Api\V1\Admin\Services\RestoreServiceRequest;
use App\Http\Requests\Api\V1\Admin\Services\StoreServiceRequest;
use App\Http\Requests\Api\V1\Admin\Services\UpdateServiceRequest;
use App\Http\Resources\Api\V1\Admin\Services\ServiceIndexResource;
use App\Http\Resources\Api\V1\Admin\Services\ServiceResource;
use App\Queries\Services\AdminServiceIndexQuery;
use App\Services\Services\ServiceLookupService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private readonly AdminServiceIndexQuery $adminServiceIndexQuery,
        private readonly ServiceLookupService $serviceLookupService,
        private readonly CreateServiceAction $createServiceAction,
        private readonly UpdateServiceAction $updateServiceAction,
        private readonly DeleteServiceAction $deleteServiceAction,
        private readonly RestoreServiceAction $restoreServiceAction,
    ) {}

    public function index(ListServicesRequest $request): JsonResponse
    {
        $services = $this->adminServiceIndexQuery->paginate($request->filters());

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('services.listed'),
                'data' => ServiceIndexResource::collection(collect($services->items()))->resolve($request),
                'meta' => [
                    'currentPage' => $services->currentPage(),
                    'perPage' => $services->perPage(),
                    'total' => $services->total(),
                    'lastPage' => $services->lastPage(),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $this->createServiceAction->execute($request->payload());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('services.created'),
                (new ServiceResource($this->serviceLookupService->loadDetailOrFail($service->getKey())))->resolve($request),
                HttpStatusCode::CREATED,
            ),
        );
    }

    public function show(Request $request, int|string $service): JsonResponse
    {
        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('services.retrieved'),
                (new ServiceResource($this->serviceLookupService->loadDetailOrFail((int) $service)))->resolve($request),
            ),
        );
    }

    public function update(UpdateServiceRequest $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $updatedService = $this->updateServiceAction->execute($resolvedService, $request->payload());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('services.updated'),
                (new ServiceResource($this->serviceLookupService->loadDetailOrFail($updatedService->getKey())))->resolve($request),
            ),
        );
    }

    public function destroy(int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $this->deleteServiceAction->execute($resolvedService);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('services.deleted'),
                null,
            ),
        );
    }

    public function restore(RestoreServiceRequest $request, int|string $service): JsonResponse
    {
        $resolvedService = $this->serviceLookupService->findOrFail((int) $service, true);
        $restoredService = $this->restoreServiceAction->execute($resolvedService);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('services.restored'),
                (new ServiceResource($this->serviceLookupService->loadDetailOrFail($restoredService->getKey())))->resolve($request),
            ),
        );
    }
}
