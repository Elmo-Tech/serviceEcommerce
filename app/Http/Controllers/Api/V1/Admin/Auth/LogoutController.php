<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\LogoutAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\Auth\LogoutSuccessResource;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __construct(
        private readonly LogoutAdminAction $logoutAdminAction,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->logoutAdminAction->execute($user, $request->ip());
        $resource = new LogoutSuccessResource([]);

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.logout_success'),
                $resource->resolve($request),
            ),
        );
    }
}
