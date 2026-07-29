<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\LoginAdminAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\LoginRequest;
use App\Http\Resources\Api\V1\Admin\Auth\LoginSuccessResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginAdminAction $loginAdminAction,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $result = $this->loginAdminAction->execute(
            $request->normalizedEmail(),
            (string) $request->input('password'),
            $request->ip(),
        );

        if ($result['status'] === 'invalid_credentials') {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.invalid_credentials'),
                    'INVALID_CREDENTIALS',
                    null,
                    HttpStatusCode::UNAUTHORIZED,
                ),
            );
        }

        if ($result['status'] === 'inactive') {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.user_inactive'),
                    'USER_INACTIVE',
                    null,
                    HttpStatusCode::FORBIDDEN,
                ),
            );
        }

        $resource = new LoginSuccessResource([
            'accessToken' => $result['accessToken'],
            'refreshToken' => $result['refreshToken'],
            'tokenType' => $result['tokenType'],
            'tokenExpiresIn' => $result['tokenExpiresIn'],
            'refreshTokenExpiresIn' => $result['refreshTokenExpiresIn'],
            'user' => $result['user'],
        ]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.login_success'),
                $resource->resolve($request),
            ),
        );
    }
}
