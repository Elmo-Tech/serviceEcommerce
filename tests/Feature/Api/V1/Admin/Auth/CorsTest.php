<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows approved preflight and actual admin-auth requests without credentialed cors', function () {
    $origin = (string) config('services.admin_frontend.origins.0');

    $preflight = $this->call('OPTIONS', '/api/v1/admin/auth/login', [], [], [], [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, Content-Type, Accept-Language',
    ]);

    $preflight->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeaderMissing('Access-Control-Allow-Credentials');

    expect($preflight->headers->get('Access-Control-Allow-Origin'))->not->toBe('*')
        ->and($preflight->headers->get('Access-Control-Allow-Methods'))->toContain('GET', 'POST', 'PUT', 'PATCH', 'OPTIONS')
        ->and(strtolower((string) $preflight->headers->get('Access-Control-Allow-Headers')))->toContain('authorization', 'content-type', 'accept-language');

    $actual = $this->call('POST', '/api/v1/admin/auth/login', [], [], [], [
        'HTTP_ORIGIN' => $origin,
        'HTTP_ACCEPT_LANGUAGE' => 'en',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], json_encode([
        'email' => 'missing-email',
    ]));

    $actual->assertStatus(422)
        ->assertHeader('Access-Control-Allow-Origin', $origin)
        ->assertHeaderMissing('Access-Control-Allow-Credentials');

    expect(strtolower((string) $actual->headers->get('Access-Control-Expose-Headers')))->toContain('content-language');
});

it('rejects unapproved origins without wildcard or arbitrary origin reflection', function () {
    $response = $this->call('OPTIONS', '/api/v1/admin/auth/login', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example.test',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Authorization, Content-Type, Accept-Language',
    ]);

    $response->assertSuccessful()
        ->assertHeaderMissing('Access-Control-Allow-Credentials');

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('*')
        ->not->toBe('https://evil.example.test');
});
