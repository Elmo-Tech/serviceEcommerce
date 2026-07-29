<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Auth\ChangePasswordController;
use App\Http\Controllers\Api\V1\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Admin\Auth\LoginController;
use App\Http\Controllers\Api\V1\Admin\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Admin\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Admin\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Admin\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Admin\Auth\VerifyForgotPasswordCodeController;
use Illuminate\Support\Facades\Route;

it('records the canonical admin-auth route architecture and middleware ownership', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/auth'))
        ->reject(fn ($route) => str_contains($route->uri(), '_'));

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($routes)->toHaveCount(9)
        ->and($routes->pluck('uri')->unique()->values()->all())->toBe([
            'api/v1/admin/auth/login',
            'api/v1/admin/auth/refresh',
            'api/v1/admin/auth/forgot-password',
            'api/v1/admin/auth/verify-forgot-password-code',
            'api/v1/admin/auth/reset-password',
            'api/v1/admin/auth/logout',
            'api/v1/admin/auth/profile',
            'api/v1/admin/auth/change-password',
        ])
        ->and($signatures)->toBe([
            'GET|HEAD api/v1/admin/auth/profile',
            'PATCH api/v1/admin/auth/profile',
            'POST api/v1/admin/auth/forgot-password',
            'POST api/v1/admin/auth/login',
            'POST api/v1/admin/auth/logout',
            'POST api/v1/admin/auth/refresh',
            'POST api/v1/admin/auth/reset-password',
            'POST api/v1/admin/auth/verify-forgot-password-code',
            'PUT api/v1/admin/auth/change-password',
        ]);

    expect(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/login', 'POST'))->getActionName())
        ->toBe(LoginController::class)
        ->and(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/refresh', 'POST'))->getActionName())
        ->toBe(RefreshTokenController::class)
        ->and(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/logout', 'POST'))->getActionName())
        ->toBe(LogoutController::class)
        ->and(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/profile', 'GET'))->getActionName())
        ->toBe(ProfileController::class.'@show')
        ->and(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/profile', 'PATCH'))->getActionName())
        ->toBe(ProfileController::class.'@update')
        ->and(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/change-password', 'PUT'))->getActionName())
        ->toBe(ChangePasswordController::class)
        ->and(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/forgot-password', 'POST'))->getActionName())
        ->toBe(ForgotPasswordController::class)
        ->and(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/verify-forgot-password-code', 'POST'))->getActionName())
        ->toBe(VerifyForgotPasswordCodeController::class)
        ->and(Route::getRoutes()->match(request()->create('/api/v1/admin/auth/reset-password', 'POST'))->getActionName())
        ->toBe(ResetPasswordController::class);
});

it('keeps feature 001 within the approved actor boundary and without forbidden legacy concepts', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->values();

    expect($routes->filter(fn ($uri) => str_contains($uri, 'api/v1/public/auth')))->toBeEmpty()
        ->and($routes->filter(fn ($uri) => str_contains($uri, 'api/v1/customer/auth')))->toBeEmpty()
        ->and($routes->filter(fn ($uri) => str_contains($uri, 'api/v1/admin/auth/csrf')))->toBeEmpty()
        ->and($routes->filter(fn ($uri) => str_contains($uri, 'api/v1/admin/auth/origin')))->toBeEmpty()
        ->and(file_exists(app_path('Http/Middleware/ValidateAdminOrigin.php')))->toBeFalse()
        ->and(file_exists(app_path('Http/Middleware/ValidateAdminCsrfToken.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/Auth/AdminAuthCookieService.php')))->toBeFalse()
        ->and(file_exists(app_path('Services/Auth/AdminCsrfService.php')))->toBeFalse();

    $protectedRoutes = [
        Route::getRoutes()->match(request()->create('/api/v1/admin/auth/logout', 'POST')),
        Route::getRoutes()->match(request()->create('/api/v1/admin/auth/profile', 'GET')),
        Route::getRoutes()->match(request()->create('/api/v1/admin/auth/profile', 'PATCH')),
        Route::getRoutes()->match(request()->create('/api/v1/admin/auth/change-password', 'PUT')),
    ];

    foreach ($protectedRoutes as $route) {
        $middleware = $route->gatherMiddleware();
        $stack = array_values(array_intersect($middleware, ['auth:sanctum', 'admin.user_type', 'admin.active']));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($stack)->toBe(['auth:sanctum', 'admin.user_type', 'admin.active'])
            ->and($middleware)->not->toContain('admin.origin', 'admin.csrf');
    }
});
