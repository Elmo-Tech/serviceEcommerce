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

it('exposes exactly the approved nine canonical admin-auth operations and no alternates', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/auth'))
        ->reject(fn ($route) => str_contains($route->uri(), '_login-test'));

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($routes)->toHaveCount(9)
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
});

it('assigns the expected controllers and middleware stacks to every protected and refresh route', function () {
    $refreshRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/refresh', 'POST'));
    $logoutRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/logout', 'POST'));
    $showProfileRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/profile', 'GET'));
    $updateProfileRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/profile', 'PATCH'));
    $changePasswordRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/change-password', 'PUT'));
    $forgotPasswordRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/forgot-password', 'POST'));
    $verifyCodeRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/verify-forgot-password-code', 'POST'));
    $resetPasswordRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/reset-password', 'POST'));
    $loginRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/auth/login', 'POST'));

    expect($loginRoute->getActionName())->toBe(LoginController::class)
        ->and($refreshRoute->getActionName())->toBe(RefreshTokenController::class)
        ->and($logoutRoute->getActionName())->toBe(LogoutController::class)
        ->and($showProfileRoute->getActionName())->toBe(ProfileController::class.'@show')
        ->and($updateProfileRoute->getActionName())->toBe(ProfileController::class.'@update')
        ->and($changePasswordRoute->getActionName())->toBe(ChangePasswordController::class)
        ->and($forgotPasswordRoute->getActionName())->toBe(ForgotPasswordController::class)
        ->and($verifyCodeRoute->getActionName())->toBe(VerifyForgotPasswordCodeController::class)
        ->and($resetPasswordRoute->getActionName())->toBe(ResetPasswordController::class);

    $refreshMiddleware = $refreshRoute->gatherMiddleware();
    $logoutMiddleware = $logoutRoute->gatherMiddleware();
    $showProfileMiddleware = $showProfileRoute->gatherMiddleware();
    $updateProfileMiddleware = $updateProfileRoute->gatherMiddleware();
    $changePasswordMiddleware = $changePasswordRoute->gatherMiddleware();

    expect($refreshMiddleware)->toContain(
        'admin.auth.headers',
        'throttle:admin-refresh',
    )
        ->and($refreshMiddleware)->not->toContain('admin.origin', 'admin.csrf')
        ->and($logoutMiddleware)->toContain(
            'admin.auth.headers',
            'auth:sanctum',
            'admin.user_type',
            'admin.active',
        )->and($showProfileMiddleware)->toContain(
            'admin.auth.headers',
            'auth:sanctum',
            'admin.user_type',
            'admin.active',
        )->and($updateProfileMiddleware)->toContain(
            'admin.auth.headers',
            'auth:sanctum',
            'admin.user_type',
            'admin.active',
        )->and($changePasswordMiddleware)->toContain(
            'admin.auth.headers',
            'auth:sanctum',
            'admin.user_type',
            'admin.active',
            'throttle:admin-change-password',
        );
});
