<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\VerifyForgotPasswordCodeAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\VerifyForgotPasswordCodeRequest;
use App\Http\Resources\Api\V1\Admin\Auth\VerifyForgotPasswordCodeSuccessResource;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class VerifyForgotPasswordCodeController extends Controller
{
    public function __construct(
        private readonly VerifyForgotPasswordCodeAction $verifyForgotPasswordCodeAction,
    ) {}

    public function __invoke(VerifyForgotPasswordCodeRequest $request): JsonResponse
    {
        $result = $this->verifyForgotPasswordCodeAction->execute(
            $request->normalizedEmail(),
            (string) $request->input('code'),
            $request->ip(),
        );

        if ($result['status'] !== 'success') {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.password_reset_code_invalid'),
                    'PASSWORD_RESET_CODE_INVALID',
                    null,
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                ),
            );
        }

        $resource = new VerifyForgotPasswordCodeSuccessResource([
            'resetToken' => $result['resetToken'],
            'resetTokenExpiresIn' => $result['resetTokenExpiresIn'],
        ]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.reset_code_verified'),
                $resource->resolve($request),
            ),
        );
    }
}
