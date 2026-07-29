<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\HttpStatusCode;
use App\Models\User;
use App\Support\Api\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdministrator()) {
            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __('auth.forbidden'),
                    'FORBIDDEN',
                    null,
                    HttpStatusCode::FORBIDDEN,
                ),
            );
        }

        return $next($request);
    }
}
