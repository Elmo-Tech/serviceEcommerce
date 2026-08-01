<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Categories\CategoryController;
use App\Http\Controllers\Api\V1\Admin\Categories\SubcategoryController;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerAddressController;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Admin\Services\ServiceController;
use App\Http\Controllers\Api\V1\Admin\Services\ServiceMediaController;
use App\Http\Controllers\Api\V1\Admin\Services\ServiceOrderFieldController;
use App\Http\Controllers\Api\V1\Admin\Services\ServicePricingOptionController;
use App\Http\Controllers\Api\V1\Admin\Services\ServiceSpecificationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'admin.auth.headers',
    'auth:sanctum',
    'admin.user_type',
    'admin.active',
])->group(function (): void {
    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:customers.view');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('permission:customers.create');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->middleware('permission:customers.view');
    Route::patch('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('permission:customers.delete');
    Route::post('/customers/{customer}/restore', [CustomerController::class, 'restore'])
        ->middleware('permission:customers.restore');

    Route::get('/customers/{customer}/addresses', [CustomerAddressController::class, 'index'])
        ->middleware('permission:customer-addresses.view');
    Route::post('/customers/{customer}/addresses', [CustomerAddressController::class, 'store'])
        ->middleware('permission:customer-addresses.create');
    Route::get('/customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'show'])
        ->middleware('permission:customer-addresses.view');
    Route::patch('/customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'update'])
        ->middleware('permission:customer-addresses.update');
    Route::delete('/customers/{customer}/addresses/{address}', [CustomerAddressController::class, 'destroy'])
        ->middleware('permission:customer-addresses.delete');
    Route::post('/customers/{customer}/addresses/{address}/restore', [CustomerAddressController::class, 'restore'])
        ->middleware('permission:customer-addresses.restore');
    Route::put('/customers/{customer}/addresses/{address}/default', [CustomerAddressController::class, 'setDefault'])
        ->middleware('permission:customer-addresses.set-default');

    Route::get('/categories', [CategoryController::class, 'index'])
        ->middleware('permission:categories.view');
    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware('permission:categories.create');
    Route::patch('/categories/reorder', [CategoryController::class, 'reorder'])
        ->middleware('permission:categories.reorder');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])
        ->whereNumber('category')
        ->middleware('permission:categories.view');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])
        ->whereNumber('category')
        ->middleware('permission:categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->whereNumber('category')
        ->middleware('permission:categories.delete');
    Route::post('/categories/{category}/restore', [CategoryController::class, 'restore'])
        ->whereNumber('category')
        ->middleware('permission:categories.restore');

    Route::get('/services', [ServiceController::class, 'index'])
        ->middleware('permission:services.view');
    Route::post('/services', [ServiceController::class, 'store'])
        ->middleware('permission:services.create');
    Route::post('/services/{service}/restore', [ServiceController::class, 'restore'])
        ->whereNumber('service')
        ->middleware('permission:services.restore');
    Route::get('/services/{service}', [ServiceController::class, 'show'])
        ->whereNumber('service')
        ->middleware('permission:services.view');
    Route::patch('/services/{service}', [ServiceController::class, 'update'])
        ->whereNumber('service')
        ->middleware('permission:services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])
        ->whereNumber('service')
        ->middleware('permission:services.delete');

    Route::get('/services/{service}/specifications', [ServiceSpecificationController::class, 'index'])
        ->whereNumber('service')
        ->middleware('permission:service-specifications.view');
    Route::post('/services/{service}/specifications', [ServiceSpecificationController::class, 'store'])
        ->whereNumber('service')
        ->middleware('permission:service-specifications.create');
    Route::get('/services/{service}/specifications/{specification}', [ServiceSpecificationController::class, 'show'])
        ->whereNumber('service')
        ->whereNumber('specification')
        ->middleware('permission:service-specifications.view');
    Route::patch('/services/{service}/specifications/{specification}', [ServiceSpecificationController::class, 'update'])
        ->whereNumber('service')
        ->whereNumber('specification')
        ->middleware('permission:service-specifications.update');
    Route::delete('/services/{service}/specifications/{specification}', [ServiceSpecificationController::class, 'destroy'])
        ->whereNumber('service')
        ->whereNumber('specification')
        ->middleware('permission:service-specifications.delete');

    Route::get('/services/{service}/order-fields', [ServiceOrderFieldController::class, 'index'])
        ->whereNumber('service')
        ->middleware('permission:service-order-fields.view');
    Route::post('/services/{service}/order-fields', [ServiceOrderFieldController::class, 'store'])
        ->whereNumber('service')
        ->middleware('permission:service-order-fields.create');
    Route::get('/services/{service}/order-fields/{orderField}', [ServiceOrderFieldController::class, 'show'])
        ->whereNumber('service')
        ->whereNumber('orderField')
        ->middleware('permission:service-order-fields.view');
    Route::patch('/services/{service}/order-fields/{orderField}', [ServiceOrderFieldController::class, 'update'])
        ->whereNumber('service')
        ->whereNumber('orderField')
        ->middleware('permission:service-order-fields.update');
    Route::delete('/services/{service}/order-fields/{orderField}', [ServiceOrderFieldController::class, 'destroy'])
        ->whereNumber('service')
        ->whereNumber('orderField')
        ->middleware('permission:service-order-fields.delete');

    Route::get('/services/{service}/pricing-options', [ServicePricingOptionController::class, 'index'])
        ->whereNumber('service')
        ->middleware('permission:service-pricing-options.view');
    Route::post('/services/{service}/pricing-options', [ServicePricingOptionController::class, 'store'])
        ->whereNumber('service')
        ->middleware('permission:service-pricing-options.create');
    Route::get('/services/{service}/pricing-options/{pricingOption}', [ServicePricingOptionController::class, 'show'])
        ->whereNumber('service')
        ->whereNumber('pricingOption')
        ->middleware('permission:service-pricing-options.view');
    Route::patch('/services/{service}/pricing-options/{pricingOption}', [ServicePricingOptionController::class, 'update'])
        ->whereNumber('service')
        ->whereNumber('pricingOption')
        ->middleware('permission:service-pricing-options.update');
    Route::delete('/services/{service}/pricing-options/{pricingOption}', [ServicePricingOptionController::class, 'destroy'])
        ->whereNumber('service')
        ->whereNumber('pricingOption')
        ->middleware('permission:service-pricing-options.delete');

    Route::get('/services/{service}/media', [ServiceMediaController::class, 'index'])
        ->whereNumber('service')
        ->middleware('permission:service-media.view');
    Route::post('/services/{service}/media', [ServiceMediaController::class, 'store'])
        ->whereNumber('service')
        ->middleware('permission:service-media.create');
    Route::patch('/services/{service}/media/{media}/set-as-main', [ServiceMediaController::class, 'setAsMain'])
        ->whereNumber('service')
        ->whereNumber('media')
        ->middleware('permission:service-media.set-main');
    Route::patch('/services/{service}/media/{media}', [ServiceMediaController::class, 'update'])
        ->whereNumber('service')
        ->whereNumber('media')
        ->middleware('permission:service-media.update');
    Route::delete('/services/{service}/media/{media}', [ServiceMediaController::class, 'destroy'])
        ->whereNumber('service')
        ->whereNumber('media')
        ->middleware('permission:service-media.delete');

    Route::get('/categories/{category}/subcategories', [SubcategoryController::class, 'index'])
        ->whereNumber('category')
        ->middleware('permission:subcategories.view');
    Route::post('/categories/{category}/subcategories', [SubcategoryController::class, 'store'])
        ->whereNumber('category')
        ->middleware('permission:subcategories.create');
    Route::patch('/categories/{category}/subcategories/reorder', [SubcategoryController::class, 'reorder'])
        ->whereNumber('category')
        ->middleware('permission:subcategories.reorder');
    Route::get('/categories/{category}/subcategories/{subcategory}', [SubcategoryController::class, 'show'])
        ->whereNumber('category')
        ->whereNumber('subcategory')
        ->middleware('permission:subcategories.view');
    Route::patch('/categories/{category}/subcategories/{subcategory}', [SubcategoryController::class, 'update'])
        ->whereNumber('category')
        ->whereNumber('subcategory')
        ->middleware('permission:subcategories.update');
    Route::delete('/categories/{category}/subcategories/{subcategory}', [SubcategoryController::class, 'destroy'])
        ->whereNumber('category')
        ->whereNumber('subcategory')
        ->middleware('permission:subcategories.delete');
    Route::post('/categories/{category}/subcategories/{subcategory}/restore', [SubcategoryController::class, 'restore'])
        ->whereNumber('category')
        ->whereNumber('subcategory')
        ->middleware('permission:subcategories.restore');
});
