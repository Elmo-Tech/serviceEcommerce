<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Settings;

use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Public\Settings\PublicSettingsResource;
use App\Services\Settings\SettingsResolver;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsResolver $settingsResolver,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $setting = $this->settingsResolver->resolveForPublic();

        $resource = $setting === null
            ? new PublicSettingsResource($this->settingsResolver->safePublicDefaults(app()->getLocale()))
            : new PublicSettingsResource($setting->load(['phones', 'socialLinks']));

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('settings.retrieved'),
                $resource->resolve($request),
                HttpStatusCode::OK,
            ),
        );
    }
}
