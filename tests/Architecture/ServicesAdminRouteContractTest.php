<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('records the exact admin service core routes with the approved middleware order and independent permissions', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(function ($route): bool {
            $uri = $route->uri();

            return in_array($uri, [
                'api/v1/admin/services',
                'api/v1/admin/services/{service}',
                'api/v1/admin/services/{service}/restore',
            ], true);
        });

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toBe([
        'DELETE api/v1/admin/services/{service}',
        'GET|HEAD api/v1/admin/services',
        'GET|HEAD api/v1/admin/services/{service}',
        'PATCH api/v1/admin/services/{service}',
        'POST api/v1/admin/services',
        'POST api/v1/admin/services/{service}/restore',
    ]);

    $listRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services', 'GET'));
    $storeRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services', 'POST'));
    $showRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1', 'GET'));
    $updateRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1', 'PATCH'));
    $deleteRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1', 'DELETE'));
    $restoreRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1/restore', 'POST'));

    $protectedStack = ['auth:sanctum', 'admin.user_type', 'admin.active'];

    foreach ([$listRoute, $storeRoute, $showRoute, $updateRoute, $deleteRoute, $restoreRoute] as $route) {
        $middleware = $route->gatherMiddleware();
        $stack = array_values(array_intersect($middleware, $protectedStack));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($stack)->toBe($protectedStack);
    }

    expect($listRoute->gatherMiddleware())->toContain('permission:services.view')
        ->and($storeRoute->gatherMiddleware())->toContain('permission:services.create')
        ->and($showRoute->gatherMiddleware())->toContain('permission:services.view')
        ->and($updateRoute->gatherMiddleware())->toContain('permission:services.update')
        ->and($deleteRoute->gatherMiddleware())->toContain('permission:services.delete')
        ->and($restoreRoute->gatherMiddleware())->toContain('permission:services.restore');
});
