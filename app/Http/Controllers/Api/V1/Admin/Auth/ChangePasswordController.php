<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\ChangeAdminPasswordAction;
use App\Enums\HttpStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\ChangePasswordRequest;
use App\Http\Resources\Api\V1\Admin\Auth\ChangePasswordSuccessResource;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ChangePasswordController extends Controller
{
    public function __construct(
        private readonly ChangeAdminPasswordAction $changeAdminPasswordAction,
    ) {}

    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->changeAdminPasswordAction->execute(
            $user,
            (string) $request->input('currentPassword'),
            (string) $request->input('password'),
            $request->ip(),
        );

        if ($result['status'] === 'current_password_invalid') {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.current_password_invalid'),
                    'CURRENT_PASSWORD_INVALID',
                    [
                        'currentPassword' => [__('auth.current_password_invalid')],
                    ],
                    HttpStatusCode::UNPROCESSABLE_ENTITY,
                ),
            );
        }

        $resource = new ChangePasswordSuccessResource([]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.password_changed'),
                $resource->resolve($request),
            ),
        );
    }
}
