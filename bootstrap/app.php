<?php

use App\Enums\HttpStatusCode;
use App\Exceptions\ApiBusinessException;
use App\Http\Middleware\ApplyAuthenticationResponseHeaders;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Http\Middleware\ResolveApiLocale;
use App\Support\Api\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings([
            'password',
            'passwordConfirmation',
            'currentPassword',
            'refreshToken',
            'code',
            'resetToken',
        ]);

        $middleware->api(prepend: [
            ResolveApiLocale::class,
        ]);

        $middleware->alias([
            'admin.auth.headers' => ApplyAuthenticationResponseHeaders::class,
            'admin.user_type' => EnsureUserIsAdministrator::class,
            'admin.active' => EnsureAdminIsActive::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $resolveLocale = static function (Request $request): string {
            $resolvedLocale = $request->attributes->get('resolvedLocale');

            if (is_string($resolvedLocale) && $resolvedLocale !== '') {
                return $resolvedLocale;
            }

            $acceptLanguage = $request->header('Accept-Language');

            if (is_string($acceptLanguage) && $acceptLanguage !== '') {
                foreach (explode(',', $acceptLanguage) as $candidate) {
                    $language = strtolower(trim(explode(';', $candidate)[0] ?? ''));
                    $normalized = explode('-', $language)[0];

                    if (in_array($normalized, ['ar', 'en'], true)) {
                        $request->attributes->set('resolvedLocale', $normalized);

                        return $normalized;
                    }
                }
            }

            $fallbackLocale = (string) config('app.locale', 'ar');
            $request->attributes->set('resolvedLocale', $fallbackLocale);

            return $fallbackLocale;
        };

        $apiError = static function (
            Request $request,
            string $translationKey,
            string $code,
            HttpStatusCode $status,
            mixed $errors = null,
        ) use ($resolveLocale) {
            $locale = $resolveLocale($request);
            app()->setLocale($locale);

            return ApiResponse::withAuthenticationHeaders(
                ApiResponse::error(
                    __($translationKey),
                    $code,
                    $errors,
                    $status,
                ),
                $locale,
            );
        };

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash([
            'password',
            'passwordConfirmation',
            'currentPassword',
            'code',
            'resetToken',
        ]);

        $exceptions->render(function (ValidationException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                'validation.invalid_payload',
                'VALIDATION_ERROR',
                HttpStatusCode::UNPROCESSABLE_ENTITY,
                $exception->errors(),
            );
        });

        $exceptions->render(function (ApiBusinessException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                $exception->translationKey(),
                $exception->machineCode(),
                $exception->status(),
                $exception->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                'auth.unauthenticated',
                'UNAUTHENTICATED',
                HttpStatusCode::UNAUTHORIZED,
            );
        });

        $exceptions->render(function (SpatieUnauthorizedException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                'auth.forbidden',
                'FORBIDDEN',
                HttpStatusCode::FORBIDDEN,
            );
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                'auth.forbidden',
                'FORBIDDEN',
                HttpStatusCode::FORBIDDEN,
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                'auth.resource_not_found',
                'RESOURCE_NOT_FOUND',
                HttpStatusCode::NOT_FOUND,
            );
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                'auth.method_not_allowed',
                'METHOD_NOT_ALLOWED',
                HttpStatusCode::METHOD_NOT_ALLOWED,
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                'auth.rate_limited',
                'RATE_LIMITED',
                HttpStatusCode::TOO_MANY_REQUESTS,
            );
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($apiError) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return $apiError(
                $request,
                'auth.server_error',
                'INTERNAL_SERVER_ERROR',
                HttpStatusCode::INTERNAL_SERVER_ERROR,
            );
        });
    })->create();
