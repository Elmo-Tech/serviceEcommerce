<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\RefreshAdminSessionAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\RefreshTokenRequest;
use App\Http\Resources\Api\V1\Admin\Auth\RefreshSuccessResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class RefreshTokenController extends Controller
{
    public function __construct(
        private readonly RefreshAdminSessionAction $refreshAdminSessionAction,
    ) {}

    public function __invoke(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->refreshAdminSessionAction->execute(
            (string) $request->input('refreshToken'),
            $request->ip(),
        );

        if ($result['status'] !== 'success') {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.refresh_token_invalid'),
                    'REFRESH_TOKEN_INVALID',
                    null,
                    HttpStatusCode::UNAUTHORIZED,
                ),
            );
        }

        $resource = new RefreshSuccessResource([
            'accessToken' => $result['accessToken'],
            'refreshToken' => $result['refreshToken'],
            'tokenType' => $result['tokenType'],
            'tokenExpiresIn' => $result['tokenExpiresIn'],
            'refreshTokenExpiresIn' => $result['refreshTokenExpiresIn'],
        ]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.refresh_success'),
                $resource->resolve($request),
            ),
        );
    }
}
