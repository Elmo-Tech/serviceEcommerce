<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Auth\UpdateAdminProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\Admin\Auth\AdminProfileResource;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UpdateAdminProfileAction $updateAdminProfileAction,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.profile_retrieved'),
                (new AdminProfileResource($user->fresh(['roles', 'permissions'])))->resolve($request),
            ),
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updatedUser = $this->updateAdminProfileAction->execute(
            $user,
            $request->validated(),
            $request->avatarUpload(),
            $request->preservesCurrentAvatar(),
        );

        return ApiResponse::withAuthenticationHeaders(
            ApiResponse::success(
                __('auth.profile_updated'),
                (new AdminProfileResource($updatedUser))->resolve($request),
            ),
        );
    }
}
