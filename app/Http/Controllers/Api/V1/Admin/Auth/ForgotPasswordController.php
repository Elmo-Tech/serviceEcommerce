<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\SendForgotPasswordCodeAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\ForgotPasswordRequest;
use App\Http\Resources\Api\V1\Admin\Auth\ForgotPasswordSuccessResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ForgotPasswordController extends Controller
{
    public function __construct(
        private readonly SendForgotPasswordCodeAction $sendForgotPasswordCodeAction,
    ) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->sendForgotPasswordCodeAction->execute(
            $request->normalizedEmail(),
            $request->ip(),
        );

        if ($result['status'] === 'mail_unavailable') {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.mail_service_unavailable'),
                    'MAIL_SERVICE_UNAVAILABLE',
                    null,
                    HttpStatusCode::SERVICE_UNAVAILABLE,
                ),
            );
        }

        if ($result['status'] === 'rate_limited') {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.rate_limited'),
                    'RATE_LIMITED',
                    null,
                    HttpStatusCode::TOO_MANY_REQUESTS,
                ),
            );
        }

        $resource = new ForgotPasswordSuccessResource([]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.forgot_password_accepted'),
                $resource->resolve($request),
            ),
        );
    }
}
