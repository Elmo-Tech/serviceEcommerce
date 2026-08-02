<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Actions\Settings\UpdateSettingsAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Settings\UpdateSettingsRequest;
use App\Http\Resources\Api\V1\Admin\Settings\AdminSettingsResource;
use App\Services\Settings\SettingsResolver;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsResolver $settingsResolver,
        private readonly UpdateSettingsAction $updateSettingsAction,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $setting = DB::transaction(fn () => $this->settingsResolver->resolveForAdmin(lockForUpdate: true)->load(['phones', 'socialLinks']));

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('settings.retrieved'),
                (new AdminSettingsResource($setting))->resolve($request),
                HttpStatusCode::OK,
            ),
        );
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $setting = $this->updateSettingsAction->execute($request->payload())->load(['phones', 'socialLinks']);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('settings.updated'),
                (new AdminSettingsResource($setting))->resolve($request),
                HttpStatusCode::OK,
            ),
        );
    }
}
