<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Dashboard;

use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Dashboard\ShowDashboardRequest;
use App\Http\Resources\Api\V1\Admin\Dashboard\DashboardResource;
use App\Queries\Dashboard\DashboardAnalyticsQuery;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardAnalyticsQuery $dashboardAnalyticsQuery,
    ) {}

    public function show(ShowDashboardRequest $request): JsonResponse
    {
        $dashboard = $this->dashboardAnalyticsQuery->execute($request->filters());

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('dashboard.retrieved'),
                (new DashboardResource($dashboard))->resolve($request),
                HttpStatusCode::OK,
            ),
        );
    }
}
