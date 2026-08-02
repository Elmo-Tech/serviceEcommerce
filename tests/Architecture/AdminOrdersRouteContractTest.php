<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers the approved admin order read and workflow routes with the protected middleware stack and independent permissions', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/orders'));

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toContain(
        'GET|HEAD api/v1/admin/orders',
        'GET|HEAD api/v1/admin/orders/{order}',
        'PATCH api/v1/admin/orders/{order}',
        'DELETE api/v1/admin/orders/{order}',
        'PATCH api/v1/admin/orders/{order}/status',
        'GET|HEAD api/v1/admin/orders/{order}/payment',
        'PATCH api/v1/admin/orders/{order}/payment',
        'POST api/v1/admin/orders',
    );

    $protectedStack = ['auth:sanctum', 'admin.user_type', 'admin.active'];

    $routeAssertions = [
        ['route' => Route::getRoutes()->match(request()->create('/api/v1/admin/orders', 'GET')), 'permission' => 'permission:orders.view'],
        ['route' => Route::getRoutes()->match(request()->create('/api/v1/admin/orders/1', 'GET')), 'permission' => 'permission:orders.view'],
        ['route' => Route::getRoutes()->match(request()->create('/api/v1/admin/orders/1', 'PATCH')), 'permission' => 'permission:orders.update'],
        ['route' => Route::getRoutes()->match(request()->create('/api/v1/admin/orders/1', 'DELETE')), 'permission' => 'permission:orders.delete'],
        ['route' => Route::getRoutes()->match(request()->create('/api/v1/admin/orders/1/status', 'PATCH')), 'permission' => 'permission:orders.change-status'],
        ['route' => Route::getRoutes()->match(request()->create('/api/v1/admin/orders/1/payment', 'GET')), 'permission' => 'permission:orders.manage-payment'],
        ['route' => Route::getRoutes()->match(request()->create('/api/v1/admin/orders/1/payment', 'PATCH')), 'permission' => 'permission:orders.manage-payment'],
        ['route' => Route::getRoutes()->match(request()->create('/api/v1/admin/orders', 'POST')), 'permission' => 'permission:orders.create'],
    ];

    foreach ($routeAssertions as $assertion) {
        $middleware = $assertion['route']->gatherMiddleware();
        $stack = array_values(array_intersect($middleware, $protectedStack));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($stack)->toBe($protectedStack)
            ->and($middleware)->toContain($assertion['permission']);
    }

    expect(Route::getRoutes()->match(request()->create('/api/v1/admin/orders', 'POST'))->gatherMiddleware())
        ->toContain('permission:order-items.create');
});
