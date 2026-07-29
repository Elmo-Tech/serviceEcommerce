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

Route::middleware(['admin.auth.headers'])->group(function (): void {
    Route::post('/login', LoginController::class)
        ->middleware('throttle:admin-login');

    Route::post('/refresh', RefreshTokenController::class)
        ->middleware('throttle:admin-refresh');

    Route::post('/forgot-password', ForgotPasswordController::class)
        ->middleware('throttle:admin-forgot-password');

    Route::post('/verify-forgot-password-code', VerifyForgotPasswordCodeController::class);

    Route::post('/reset-password', ResetPasswordController::class)
        ->middleware('throttle:admin-reset-password');

    Route::middleware([
        'auth:sanctum',
        'admin.user_type',
        'admin.active',
    ])->group(function (): void {
        Route::post('/logout', LogoutController::class);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::put('/change-password', ChangePasswordController::class)
            ->middleware('throttle:admin-change-password');
    });
});
