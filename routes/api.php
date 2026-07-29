<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('admin/auth')
        ->group(base_path('routes/api/v1/auth.php'));

    Route::prefix('public')->group(function (): void {
        // Public API routes are mounted by later features.
    });
});
