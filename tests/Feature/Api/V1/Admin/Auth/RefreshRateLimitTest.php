<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

it('keys refresh throttling by requester ip and token fingerprint without plaintext leakage', function () {
    $limiter = RateLimiter::limiter('admin-refresh');

    expect($limiter)->not->toBeNull();

    $refreshToken = 'refresh-token-secret';
    $request = Request::create(
        '/api/v1/admin/auth/refresh',
        'POST',
        ['refreshToken' => $refreshToken],
        server: ['REMOTE_ADDR' => '203.0.113.20'],
    );

    $limit = $limiter($request);

    expect($limit->maxAttempts)->toBe(10)
        ->and($limit->decaySeconds)->toBe(60)
        ->and($limit->key)->toBe('203.0.113.20|'.hash('sha256', $refreshToken))
        ->and($limit->key)->not->toContain($refreshToken);

    $invalidRequest = Request::create(
        '/api/v1/admin/auth/refresh',
        'POST',
        [],
        server: ['REMOTE_ADDR' => '203.0.113.20'],
    );

    expect($limiter($invalidRequest)->key)->toBe('203.0.113.20');
});

it('ignores forwarded header spoofing when deriving the refresh limiter key', function () {
    $limiter = RateLimiter::limiter('admin-refresh');

    $request = Request::create(
        '/api/v1/admin/auth/refresh',
        'POST',
        ['refreshToken' => 'refresh-token-secret'],
        server: [
            'REMOTE_ADDR' => '203.0.113.30',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.99',
        ],
    );

    expect($limiter($request)->key)->toStartWith('203.0.113.30|')
        ->and($limiter($request)->key)->not->toContain('198.51.100.99');
});

it('keeps different refresh fingerprints in separate buckets and limits the eleventh hit', function () {
    seedAdminAuthEnvironment();

    $tokenA = 'structurally-valid-token-a';
    $tokenB = 'structurally-valid-token-b';
    $url = '/api/v1/admin/auth/refresh';

    foreach (range(1, 10) as $attempt) {
        $this->postJson($url, [
            'refreshToken' => $tokenA,
        ], [
            'Accept-Language' => 'en',
        ])->assertStatus(HttpStatusCode::UNAUTHORIZED->value);
    }

    $this->postJson($url, [
        'refreshToken' => $tokenB,
    ], [
        'Accept-Language' => 'en',
    ])->assertStatus(HttpStatusCode::UNAUTHORIZED->value);

    $this->postJson($url, [
        'refreshToken' => $tokenA,
    ], [
        'Accept-Language' => 'en',
    ])->assertStatus(HttpStatusCode::TOO_MANY_REQUESTS->value)
        ->assertJsonPath('code', 'RATE_LIMITED');
});
