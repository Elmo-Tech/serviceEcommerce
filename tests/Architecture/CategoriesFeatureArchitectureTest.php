<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers the exact category feature routes with the approved boundaries and middleware order', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/categories') || str_starts_with($route->uri(), 'api/v1/public/categories'));

    $signatures = $routes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    expect($signatures)->toBe([
        'DELETE api/v1/admin/categories/{category}',
        'DELETE api/v1/admin/categories/{category}/subcategories/{subcategory}',
        'GET|HEAD api/v1/admin/categories',
        'GET|HEAD api/v1/admin/categories/{category}',
        'GET|HEAD api/v1/admin/categories/{category}/subcategories',
        'GET|HEAD api/v1/admin/categories/{category}/subcategories/{subcategory}',
        'GET|HEAD api/v1/public/categories',
        'GET|HEAD api/v1/public/categories/{categorySlug}',
        'GET|HEAD api/v1/public/categories/{categorySlug}/subcategories',
        'GET|HEAD api/v1/public/categories/{categorySlug}/subcategories/{subcategorySlug}',
        'PATCH api/v1/admin/categories/reorder',
        'PATCH api/v1/admin/categories/{category}',
        'PATCH api/v1/admin/categories/{category}/subcategories/reorder',
        'PATCH api/v1/admin/categories/{category}/subcategories/{subcategory}',
        'POST api/v1/admin/categories',
        'POST api/v1/admin/categories/{category}/restore',
        'POST api/v1/admin/categories/{category}/subcategories',
        'POST api/v1/admin/categories/{category}/subcategories/{subcategory}/restore',
    ]);

    $adminListRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/categories', 'GET'));
    $adminReorderRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/categories/reorder', 'PATCH'));
    $subcategoryReorderRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/categories/1/subcategories/reorder', 'PATCH'));
    $publicListRoute = Route::getRoutes()->match(request()->create('/api/v1/public/categories', 'GET'));

    $protectedStack = ['auth:sanctum', 'admin.user_type', 'admin.active'];

    foreach ([$adminListRoute, $adminReorderRoute, $subcategoryReorderRoute] as $route) {
        $middleware = $route->gatherMiddleware();
        $stack = array_values(array_intersect($middleware, $protectedStack));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($stack)->toBe($protectedStack);
    }

    expect($adminListRoute->gatherMiddleware())->toContain('permission:categories.view')
        ->and($adminReorderRoute->gatherMiddleware())->toContain('permission:categories.reorder')
        ->and($subcategoryReorderRoute->gatherMiddleware())->toContain('permission:subcategories.reorder')
        ->and($publicListRoute->gatherMiddleware())->not->toContain('auth:sanctum');

    $allUris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->values();

    expect($allUris->filter(fn (string $uri) => str_contains($uri, 'force-delete')))->toBeEmpty()
        ->and($allUris->filter(fn (string $uri) => str_contains($uri, '/categories/{category}/subcategories/{subcategory}/subcategories')))->toBeEmpty();
});
