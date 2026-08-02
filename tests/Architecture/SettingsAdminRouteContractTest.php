<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('records the exact admin settings routes with the approved middleware and permissions', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => $route->uri() === 'api/v1/admin/settings');

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toBe([
        'GET|HEAD api/v1/admin/settings',
        'PATCH api/v1/admin/settings',
    ]);

    $showRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/settings', 'GET'));
    $updateRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/settings', 'PATCH'));

    $protectedStack = ['auth:sanctum', 'admin.user_type', 'admin.active'];

    foreach ([$showRoute, $updateRoute] as $route) {
        $middleware = $route->gatherMiddleware();
        $stack = array_values(array_intersect($middleware, $protectedStack));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($stack)->toBe($protectedStack);
    }

    expect($showRoute->gatherMiddleware())->toContain('permission:settings.view')
        ->and($updateRoute->gatherMiddleware())->toContain('permission:settings.update');
});
