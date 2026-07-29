<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;
use App\Support\Api\ApiResponse;

it('builds the approved success envelope without undocumented error metadata', function () {
    $response = ApiResponse::success('Localized success message', [
        'tokenType' => 'Bearer',
    ]);

    expect($response->getStatusCode())->toBe(HttpStatusCode::OK->value)
        ->and($response->getData(true))->toBe([
            'success' => true,
            'message' => 'Localized success message',
            'data' => [
                'tokenType' => 'Bearer',
            ],
        ]);
});

it('builds the approved error envelope without undocumented success metadata', function () {
    $response = ApiResponse::error(
        'Localized error message',
        'VALIDATION_ERROR',
        ['email' => ['The email field is required.']],
        HttpStatusCode::UNPROCESSABLE_ENTITY,
    );

    expect($response->getStatusCode())->toBe(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
        ->and($response->getData(true))->toBe([
            'success' => false,
            'message' => 'Localized error message',
            'code' => 'VALIDATION_ERROR',
            'errors' => [
                'email' => ['The email field is required.'],
            ],
        ]);
});

it('applies authentication headers without duplicating the locale vary header', function () {
    $response = ApiResponse::withAuthenticationHeaders(
        ApiResponse::success('Localized success message', []),
        'en',
    );

    $response = ApiResponse::withAuthenticationHeaders($response, 'en');

    expect($response->headers->get('Content-Language'))->toBe('en')
        ->and($response->headers->get('Cache-Control'))->toBe('no-store, private')
        ->and($response->headers->get('Pragma'))->toBe('no-cache')
        ->and($response->headers->get('Vary'))->toBe('Accept-Language');
});
