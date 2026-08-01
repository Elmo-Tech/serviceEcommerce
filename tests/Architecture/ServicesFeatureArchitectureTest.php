<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers the exact services feature boundaries with the approved admin/public operations, constraints, middleware, and exclusions', function () {
    $serviceRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/admin/services') || str_starts_with($route->uri(), 'api/v1/public/services'));

    $signatures = $serviceRoutes
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->sort()
        ->values()
        ->all();

    $expectedAdminSignatures = [
        'DELETE api/v1/admin/services/{service}',
        'DELETE api/v1/admin/services/{service}/media/{media}',
        'DELETE api/v1/admin/services/{service}/order-fields/{orderField}',
        'DELETE api/v1/admin/services/{service}/pricing-options/{pricingOption}',
        'DELETE api/v1/admin/services/{service}/specifications/{specification}',
        'GET|HEAD api/v1/admin/services',
        'GET|HEAD api/v1/admin/services/{service}',
        'GET|HEAD api/v1/admin/services/{service}/media',
        'GET|HEAD api/v1/admin/services/{service}/order-fields',
        'GET|HEAD api/v1/admin/services/{service}/order-fields/{orderField}',
        'GET|HEAD api/v1/admin/services/{service}/pricing-options',
        'GET|HEAD api/v1/admin/services/{service}/pricing-options/{pricingOption}',
        'GET|HEAD api/v1/admin/services/{service}/specifications',
        'GET|HEAD api/v1/admin/services/{service}/specifications/{specification}',
        'PATCH api/v1/admin/services/{service}',
        'PATCH api/v1/admin/services/{service}/media/{media}',
        'PATCH api/v1/admin/services/{service}/media/{media}/set-as-main',
        'PATCH api/v1/admin/services/{service}/order-fields/{orderField}',
        'PATCH api/v1/admin/services/{service}/pricing-options/{pricingOption}',
        'PATCH api/v1/admin/services/{service}/specifications/{specification}',
        'POST api/v1/admin/services',
        'POST api/v1/admin/services/{service}/media',
        'POST api/v1/admin/services/{service}/order-fields',
        'POST api/v1/admin/services/{service}/pricing-options',
        'POST api/v1/admin/services/{service}/restore',
        'POST api/v1/admin/services/{service}/specifications',
    ];

    $expectedPublicSignatures = [
        'GET|HEAD api/v1/public/services',
        'GET|HEAD api/v1/public/services/{serviceSlug}',
    ];

    expect($signatures)->toBe(collect(array_merge($expectedAdminSignatures, $expectedPublicSignatures))->sort()->values()->all())
        ->and($expectedAdminSignatures)->toHaveCount(26)
        ->and($expectedPublicSignatures)->toHaveCount(2)
        ->and(array_unique(array_merge($expectedAdminSignatures, $expectedPublicSignatures)))->toHaveCount(28);

    $permissionBySignature = [
        'GET|HEAD api/v1/admin/services' => 'permission:services.view',
        'POST api/v1/admin/services' => 'permission:services.create',
        'POST api/v1/admin/services/{service}/restore' => 'permission:services.restore',
        'GET|HEAD api/v1/admin/services/{service}' => 'permission:services.view',
        'PATCH api/v1/admin/services/{service}' => 'permission:services.update',
        'DELETE api/v1/admin/services/{service}' => 'permission:services.delete',
        'GET|HEAD api/v1/admin/services/{service}/specifications' => 'permission:service-specifications.view',
        'POST api/v1/admin/services/{service}/specifications' => 'permission:service-specifications.create',
        'GET|HEAD api/v1/admin/services/{service}/specifications/{specification}' => 'permission:service-specifications.view',
        'PATCH api/v1/admin/services/{service}/specifications/{specification}' => 'permission:service-specifications.update',
        'DELETE api/v1/admin/services/{service}/specifications/{specification}' => 'permission:service-specifications.delete',
        'GET|HEAD api/v1/admin/services/{service}/order-fields' => 'permission:service-order-fields.view',
        'POST api/v1/admin/services/{service}/order-fields' => 'permission:service-order-fields.create',
        'GET|HEAD api/v1/admin/services/{service}/order-fields/{orderField}' => 'permission:service-order-fields.view',
        'PATCH api/v1/admin/services/{service}/order-fields/{orderField}' => 'permission:service-order-fields.update',
        'DELETE api/v1/admin/services/{service}/order-fields/{orderField}' => 'permission:service-order-fields.delete',
        'GET|HEAD api/v1/admin/services/{service}/pricing-options' => 'permission:service-pricing-options.view',
        'POST api/v1/admin/services/{service}/pricing-options' => 'permission:service-pricing-options.create',
        'GET|HEAD api/v1/admin/services/{service}/pricing-options/{pricingOption}' => 'permission:service-pricing-options.view',
        'PATCH api/v1/admin/services/{service}/pricing-options/{pricingOption}' => 'permission:service-pricing-options.update',
        'DELETE api/v1/admin/services/{service}/pricing-options/{pricingOption}' => 'permission:service-pricing-options.delete',
        'GET|HEAD api/v1/admin/services/{service}/media' => 'permission:service-media.view',
        'POST api/v1/admin/services/{service}/media' => 'permission:service-media.create',
        'PATCH api/v1/admin/services/{service}/media/{media}/set-as-main' => 'permission:service-media.set-main',
        'PATCH api/v1/admin/services/{service}/media/{media}' => 'permission:service-media.update',
        'DELETE api/v1/admin/services/{service}/media/{media}' => 'permission:service-media.delete',
    ];

    $protectedStack = ['auth:sanctum', 'admin.user_type', 'admin.active'];

    foreach ($expectedAdminSignatures as $signature) {
        [$methods, $uri] = explode(' ', $signature, 2);
        $httpMethod = str_contains($methods, 'PATCH') ? 'PATCH'
            : (str_contains($methods, 'POST') ? 'POST'
                : (str_contains($methods, 'DELETE') ? 'DELETE' : 'GET'));

        $requestUri = str_replace(
            ['{service}', '{specification}', '{orderField}', '{pricingOption}', '{media}'],
            ['1', '2', '3', '4', '5'],
            '/'.$uri,
        );

        $route = Route::getRoutes()->match(request()->create($requestUri, $httpMethod));
        $middleware = $route->gatherMiddleware();
        $stack = array_values(array_intersect($middleware, $protectedStack));

        expect($middleware)->toContain('admin.auth.headers')
            ->and($stack)->toBe($protectedStack)
            ->and($middleware)->toContain($permissionBySignature[$signature]);
    }

    $restoreRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1/restore', 'POST'));
    $setMainRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1/media/5/set-as-main', 'PATCH'));
    $specificationShowRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1/specifications/2', 'GET'));
    $orderFieldShowRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1/order-fields/3', 'GET'));
    $pricingOptionShowRoute = Route::getRoutes()->match(request()->create('/api/v1/admin/services/1/pricing-options/4', 'GET'));

    expect($restoreRoute->uri())->toBe('api/v1/admin/services/{service}/restore')
        ->and($setMainRoute->uri())->toBe('api/v1/admin/services/{service}/media/{media}/set-as-main')
        ->and($specificationShowRoute->wheres['service'] ?? null)->toBe('[0-9]+')
        ->and($specificationShowRoute->wheres['specification'] ?? null)->toBe('[0-9]+')
        ->and($orderFieldShowRoute->wheres['service'] ?? null)->toBe('[0-9]+')
        ->and($orderFieldShowRoute->wheres['orderField'] ?? null)->toBe('[0-9]+')
        ->and($pricingOptionShowRoute->wheres['service'] ?? null)->toBe('[0-9]+')
        ->and($pricingOptionShowRoute->wheres['pricingOption'] ?? null)->toBe('[0-9]+');

    foreach ($expectedPublicSignatures as $signature) {
        [$methods, $uri] = explode(' ', $signature, 2);
        $requestUri = str_replace('{serviceSlug}', 'sample-service', '/'.$uri);
        $route = Route::getRoutes()->match(request()->create($requestUri, 'GET'));

        expect($methods)->toBe('GET|HEAD')
            ->and($route->gatherMiddleware())->not->toContain('auth:sanctum')
            ->and($route->gatherMiddleware())->not->toContain('permission:services.view');
    }

    $allUris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->values();

    expect($allUris->filter(fn (string $uri) => str_contains($uri, 'api/v1/admin/services/') && str_contains($uri, '/restore') && ! str_ends_with($uri, '/services/{service}/restore')))->toBeEmpty()
        ->and($allUris->filter(fn (string $uri) => str_contains($uri, 'pricing-option-values')))->toBeEmpty()
        ->and($allUris->filter(fn (string $uri) => str_contains($uri, '/values/')))->toBeEmpty();

    $publicServiceQuery = file_get_contents(base_path('app/Queries/Services/PublicServiceIndexQuery.php'));

    expect($publicServiceQuery)->toBeString()
        ->and($publicServiceQuery)->not->toContain('allowedSorts');
});
