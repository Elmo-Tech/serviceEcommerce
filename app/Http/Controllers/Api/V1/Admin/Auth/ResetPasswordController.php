<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\ResetForgottenPasswordAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\V1\Admin\Auth\ResetPasswordSuccessResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly ResetForgottenPasswordAction $resetForgottenPasswordAction,
    ) {}

    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->resetForgottenPasswordAction->execute(
            $request->normalizedEmail(),
            (string) $request->input('resetToken'),
            (string) $request->input('password'),
            $request->ip(),
        );

        if ($result['status'] !== 'success') {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.password_reset_token_invalid'),
                    'PASSWORD_RESET_TOKEN_INVALID',
                    null,
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                ),
            );
        }

        $resource = new ResetPasswordSuccessResource([]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.password_reset_success'),
                $resource->resolve($request),
            ),
        );
    }
}
