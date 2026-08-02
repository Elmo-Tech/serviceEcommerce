<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Public\Orders\OrderController;
use Illuminate\Support\Facades\Route;

it('registers exactly the approved public order create route with the expected controller and throttle middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => $route->uri() === 'api/v1/public/orders');

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toBe([
        'POST api/v1/public/orders',
    ]);

    $route = Route::getRoutes()->match(request()->create('/api/v1/public/orders', 'POST'));
    $middleware = $route->gatherMiddleware();

    expect($route->getActionName())->toBe(OrderController::class.'@store')
        ->and($middleware)->toContain('throttle:public-orders-create')
        ->and($middleware)->not->toContain('auth:sanctum', 'permission:orders.create', 'admin.auth.headers');
});
