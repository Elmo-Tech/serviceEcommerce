<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Public\Categories\CategoryController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{categorySlug}', [CategoryController::class, 'show']);
Route::get('/categories/{categorySlug}/subcategories', [CategoryController::class, 'indexSubcategories']);
Route::get('/categories/{categorySlug}/subcategories/{subcategorySlug}', [CategoryController::class, 'showSubcategory']);
