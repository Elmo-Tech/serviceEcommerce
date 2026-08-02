<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers one protected admin-only dashboard read route', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), 'dashboard'));

    expect($routes)->toHaveCount(1);

    $route = $routes->first();
    $middleware = $route->gatherMiddleware();
    $protectedStack = ['auth:sanctum', 'admin.user_type', 'admin.active'];

    expect($route->uri())->toBe('api/v1/admin/dashboard')
        ->and($route->methods())->toBe(['GET', 'HEAD'])
        ->and($middleware)->toContain('admin.auth.headers')
        ->and(array_values(array_intersect($middleware, $protectedStack)))->toBe($protectedStack)
        ->and($middleware)->toContain('permission:dashboard.view');
});
