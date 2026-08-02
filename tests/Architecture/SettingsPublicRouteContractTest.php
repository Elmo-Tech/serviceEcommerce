<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('records the exact public settings route without admin authentication middleware', function () {
    $route = Route::getRoutes()->match(request()->create('/api/v1/public/settings', 'GET'));

    expect($route->uri())->toBe('api/v1/public/settings')
        ->and($route->methods())->toContain('GET')
        ->and($route->gatherMiddleware())->not->toContain('auth:sanctum', 'admin.auth.headers', 'permission:settings.view', 'permission:settings.update');
});
