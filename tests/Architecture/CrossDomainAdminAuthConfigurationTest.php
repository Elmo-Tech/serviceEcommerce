<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

afterEach(function (): void {
    app()->detectEnvironment(fn (): string => 'testing');
    config()->set('app.url', 'http://localhost');
    config()->set('services.admin_frontend.origin', 'https://admin.example-frontend.com');
    config()->set('services.admin_frontend.origins', ['https://admin.example-frontend.com']);
    config()->set('cors.allowed_origins', ['https://admin.example-frontend.com']);
});

it('uses an explicit origin and a relative browser api base without wildcard credentialed cors', function () {
    config()->set('services.admin_frontend.origin', 'https://admin.example-frontend.com');
    config()->set('services.admin_frontend.origins', ['https://admin.example-frontend.com', 'https://admin.example-frontend-staging.com']);
    config()->set('cors.allowed_origins', ['https://admin.example-frontend.com', 'https://admin.example-frontend-staging.com']);

    expect(config('services.admin_frontend.origin'))->toBe('https://admin.example-frontend.com')
        ->and(config('services.admin_frontend.origins'))->toBe(['https://admin.example-frontend.com', 'https://admin.example-frontend-staging.com'])
        ->and(config('cors.supports_credentials'))->toBeFalse()
        ->and(config('cors.allowed_origins'))->toBe(['https://admin.example-frontend.com', 'https://admin.example-frontend-staging.com'])
        ->and(config('cors.allowed_origins'))->not->toContain('*')
        ->and(config('cors.allowed_headers'))->toContain('Authorization')
        ->and(config('cors.allowed_headers'))->not->toContain('X-CSRF-TOKEN');
});

it('fails production validation when required admin configuration is implicit', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config()->set('app.url', 'https://api.example-backend.net');
    config()->set('services.admin_frontend.origin_configured', false);

    $provider = new AppServiceProvider(app());
    $validator = new ReflectionMethod($provider, 'validateAdminAuthenticationConfiguration');

    expect(fn () => $validator->invoke($provider))
        ->toThrow(LogicException::class, 'must be explicitly configured');
});

it('rejects an unsafe admin frontend origin', function () {
    config()->set('services.admin_frontend.origins', ['*']);

    $provider = new AppServiceProvider(app());
    $validator = new ReflectionMethod($provider, 'validateAdminAuthenticationConfiguration');

    expect(fn () => $validator->invoke($provider))
        ->toThrow(LogicException::class, 'ADMIN_FRONTEND_ORIGINS must contain only exact origins');
});

it('does not require a same-origin nginx proxy for admin authentication', function () {
    $proxy = file_get_contents(base_path('deploy/nginx/admin-frontend-api-proxy.conf.example'));

    expect($proxy)->toBeString()
        ->and($proxy)->not->toContain('proxy_set_header X-CSRF-TOKEN')
        ->and($proxy)->not->toContain('proxy_pass_header Set-Cookie')
        ->and($proxy)->not->toContain('$arg_target')
        ->and($proxy)->not->toContain('$arg_url')
        ->and($proxy)->not->toContain('$http_x_forwarded_host');
});

it('does not globally trust arbitrary laravel proxy addresses', function () {
    $bootstrap = file_get_contents(base_path('bootstrap/app.php'));

    expect($bootstrap)->toBeString()
        ->and($bootstrap)->not->toContain("trustProxies(at: '*'")
        ->and($bootstrap)->not->toContain('HEADER_X_FORWARDED_AWS_ELB');
});

it('keeps the upstream origin out of browser-public environment variables', function () {
    $environmentExample = file_get_contents(base_path('.env.example'));

    expect($environmentExample)->toBeString()
        ->and($environmentExample)->toContain('ADMIN_FRONTEND_ORIGINS=')
        ->and($environmentExample)->not->toContain('VITE_BACKEND_API_ORIGIN')
        ->and($environmentExample)->not->toContain('NEXT_PUBLIC_BACKEND_API_ORIGIN')
        ->and($environmentExample)->not->toContain('PUBLIC_BACKEND_API_ORIGIN');
});

it('uses the direct backend api postman base without cookie credentials', function () {
    $collectionJson = file_get_contents(base_path('postman/Service-Commerce.postman_collection.json'));
    $environmentJson = file_get_contents(base_path('postman/Service-Commerce.local.postman_environment.json.example'));

    expect($collectionJson)->toBeString()
        ->and($collectionJson)->not->toContain('X-CSRF-TOKEN')
        ->and($collectionJson)->not->toContain('admin_refresh_token')
        ->and($environmentJson)->toBeString()
        ->and($environmentJson)->not->toContain('upstreamApiOrigin');
});

it('keys refresh throttling by requester ip and refresh token fingerprint with invalid input fallback', function () {
    $limiter = RateLimiter::limiter('admin-refresh');

    expect($limiter)->not->toBeNull();

    $refreshToken = 'refresh-token-secret';
    $request = Request::create(
        '/api/v1/admin/auth/refresh',
        'POST',
        ['refreshToken' => $refreshToken],
        server: ['REMOTE_ADDR' => '203.0.113.10'],
    );

    $limit = $limiter($request);

    expect($limit->maxAttempts)->toBe(10)
        ->and($limit->decaySeconds)->toBe(60)
        ->and($limit->key)->toBe('203.0.113.10|'.hash('sha256', $refreshToken))
        ->and($limit->key)->not->toContain($refreshToken);

    $invalidRequest = Request::create(
        '/api/v1/admin/auth/refresh',
        'POST',
        [],
        server: ['REMOTE_ADDR' => '203.0.113.10'],
    );

    expect($limiter($invalidRequest)->key)->toBe('203.0.113.10');
});
