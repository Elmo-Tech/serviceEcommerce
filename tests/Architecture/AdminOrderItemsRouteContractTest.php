<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers the approved admin order item and attachment routes with protected middleware and independent permissions', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/orders/{order}/items'));

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toContain(
        'GET|HEAD api/v1/admin/orders/{order}/items',
        'POST api/v1/admin/orders/{order}/items',
        'GET|HEAD api/v1/admin/orders/{order}/items/{orderItem}',
        'PATCH api/v1/admin/orders/{order}/items/{orderItem}',
        'DELETE api/v1/admin/orders/{order}/items/{orderItem}',
        'POST api/v1/admin/orders/{order}/items/{orderItem}/attachments',
        'DELETE api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}',
        'GET|HEAD api/v1/admin/orders/{order}/items/{orderItem}/attachments/{attachment}/download',
    );

    $protectedStack = ['auth:sanctum', 'admin.user_type', 'admin.active'];

    $assertions = [
        ['/api/v1/admin/orders/1/items', 'GET', 'permission:order-items.view'],
        ['/api/v1/admin/orders/1/items', 'POST', 'permission:order-items.create'],
        ['/api/v1/admin/orders/1/items/2', 'GET', 'permission:order-items.view'],
        ['/api/v1/admin/orders/1/items/2', 'PATCH', 'permission:order-items.update'],
        ['/api/v1/admin/orders/1/items/2', 'DELETE', 'permission:order-items.delete'],
        ['/api/v1/admin/orders/1/items/2/attachments', 'POST', 'permission:order-item-attachments.create'],
        ['/api/v1/admin/orders/1/items/2/attachments/3', 'DELETE', 'permission:order-item-attachments.delete'],
        ['/api/v1/admin/orders/1/items/2/attachments/3/download', 'GET', 'permission:orders.view'],
    ];

    foreach ($assertions as [$uri, $method, $permission]) {
        $route = Route::getRoutes()->match(request()->create($uri, $method));
        $middleware = $route->gatherMiddleware();
        $stack = array_values(array_intersect($middleware, $protectedStack));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($stack)->toBe($protectedStack)
            ->and($middleware)->toContain($permission);
    }
});
