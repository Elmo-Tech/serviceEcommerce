<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Services;

use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Public\Services\PublicServiceListItemResource;
use App\Http\Resources\Api\V1\Public\Services\PublicServiceResource;
use App\Queries\Services\PublicServiceIndexQuery;
use App\Queries\Services\PublicServiceLookupQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private readonly PublicServiceIndexQuery $publicServiceIndexQuery,
        private readonly PublicServiceLookupQuery $publicServiceLookupQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $services = $this->publicServiceIndexQuery->paginate([
            'page' => (int) $request->input('page', 1),
            'perPage' => (int) $request->input('perPage', 12),
        ]);

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('services.listed'),
                'data' => PublicServiceListItemResource::collection(collect($services->items()))->resolve($request),
                'meta' => [
                    'currentPage' => $services->currentPage(),
                    'perPage' => $services->perPage(),
                    'total' => $services->total(),
                    'lastPage' => $services->lastPage(),
                    'locale' => app()->getLocale(),
                ],
            ], HttpStatusCode::OK->value),
        );
    }

    public function show(Request $request, string $serviceSlug): JsonResponse
    {
        $service = $this->publicServiceLookupQuery->findOrFail($serviceSlug);

        return ApiResponse::withAuthenticationHeaders(
            response()->json([
                'success' => true,
                'message' => __('services.retrieved'),
                'data' => (new PublicServiceResource($service))->resolve($request),
                'meta' => [
                    'locale' => app()->getLocale(),
                ],
            ], HttpStatusCode::OK->value),
        );
    }
}
