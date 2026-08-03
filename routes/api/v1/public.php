<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Public\Categories\CategoryController;
use App\Http\Controllers\Api\V1\Public\Faqs\FaqController;
use App\Http\Controllers\Api\V1\Public\HeroSlides\HeroSlideController;
use App\Http\Controllers\Api\V1\Public\Orders\OrderController;
use App\Http\Controllers\Api\V1\Public\Services\ServiceController;
use App\Http\Controllers\Api\V1\Public\Settings\SettingsController as PublicSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/hero-slides', [HeroSlideController::class, 'index']);
Route::get('/faqs', [FaqController::class, 'index']);
Route::get('/categories/{categorySlug}', [CategoryController::class, 'show']);
Route::get('/categories/{categorySlug}/subcategories', [CategoryController::class, 'indexSubcategories']);
Route::get('/categories/{categorySlug}/subcategories/{subcategorySlug}', [CategoryController::class, 'showSubcategory']);
Route::get('/services', [ServiceController::class, 'index']);
Route::get('/services/{serviceSlug}', [ServiceController::class, 'show']);
Route::get('/settings', [PublicSettingsController::class, 'show']);
Route::post('/orders', [OrderController::class, 'store'])
    ->middleware('throttle:public-orders-create');
