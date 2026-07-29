<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\Customers\CustomerAddressController;
use App\Http\Controllers\Api\V1\Admin\Customers\CustomerController;
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
});
