<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('admin/auth')
        ->group(base_path('routes/api/v1/auth.php'));

    Route::prefix('admin')
        ->group(base_path('routes/api/v1/admin.php'));

    Route::prefix('public')
        ->group(base_path('routes/api/v1/public.php'));
});
