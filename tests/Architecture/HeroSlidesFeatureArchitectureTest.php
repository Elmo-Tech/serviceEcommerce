<?php

declare(strict_types=1);

use App\Http\Middleware\ParseHeroSlideMultipartPatch;
use Illuminate\Support\Facades\Route;

it('registers exactly five protected admin operations and one open public operation without reorder', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains($route->uri(), 'hero-slides'))
        ->values();

    expect($routes)->toHaveCount(6)
        ->and($routes->contains(fn ($route): bool => str_contains($route->uri(), 'reorder')))->toBeFalse();

    $admin = $routes->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/admin/'));
    $public = $routes->first(fn ($route): bool => $route->uri() === 'api/v1/public/hero-slides');

    expect($admin)->toHaveCount(5)
        ->and($public)->not->toBeNull()
        ->and($public->methods())->toContain('GET');

    $expected = [
        'GET' => 'hero-slides.view',
        'POST' => 'hero-slides.create',
        'PATCH' => 'hero-slides.update',
        'DELETE' => 'hero-slides.delete',
    ];

    foreach ($admin as $route) {
        $method = collect($route->methods())->first(fn (string $value): bool => $value !== 'HEAD');
        $middleware = $route->gatherMiddleware();
        expect($middleware)->toContain('permission:'.$expected[$method]);

        if ($method === 'PATCH') {
            expect(array_search('permission:hero-slides.update', $middleware, true))
                ->toBeLessThan(array_search(ParseHeroSlideMultipartPatch::class, $middleware, true));
        }
    }
});
