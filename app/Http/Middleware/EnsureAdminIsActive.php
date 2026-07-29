<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\HttpStatusCode;
use App\Enums\RefreshTokenRevocationReason;
use App\Models\User;
use App\Services\Auth\AdminSessionRevocationService;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function __construct(
        private readonly AdminSessionRevocationService $sessionRevocationService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.forbidden'),
                    'FORBIDDEN',
                    null,
                    HttpStatusCode::FORBIDDEN,
                ),
            );
        }

        if (! $user->hasActiveAccount()) {
            $this->sessionRevocationService->revokeAllFor(
                $user,
                RefreshTokenRevocationReason::USER_INACTIVE,
            );

            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.user_inactive'),
                    'USER_INACTIVE',
                    null,
                    HttpStatusCode::FORBIDDEN,
                ),
            );
        }

        return $next($request);
    }
}
