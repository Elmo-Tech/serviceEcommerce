<?php

declare(strict_types=1);

it('keeps the frozen Hero OpenAPI operations and Postman folders synchronized', function () {
    $openApi = file_get_contents(base_path('specs/008-hero-slider-management/contracts/openapi.yaml'));
    expect($openApi)->toBeString()->toStartWith("openapi: 3.1.0\n");

    preg_match_all('/^      operationId: ([A-Za-z][A-Za-z0-9]*)$/m', (string) $openApi, $matches);
    expect($matches[1])->toBe([
        'adminListHeroSlides',
        'adminCreateHeroSlide',
        'adminShowHeroSlide',
        'adminUpdateHeroSlide',
        'adminDeleteHeroSlide',
        'publicListHeroSlides',
    ])->not->toContain('reorderHeroSlides');

    $collection = json_decode(
        (string) file_get_contents(base_path('postman/Service-Commerce.postman_collection.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect(array_column($collection['variable'], 'key'))->toBe(['baseUrl', 'accessToken', 'refreshToken']);

    $folders = collect($collection['item'])->keyBy('name');
    expect($folders)->toHaveKeys(['Admin Hero Slides', 'Public Hero Slides'])
        ->and($folders['Admin Hero Slides']['item'])->toHaveCount(5)
        ->and($folders['Public Hero Slides']['item'])->toHaveCount(1)
        ->and(json_encode($folders['Admin Hero Slides'], JSON_THROW_ON_ERROR))->not->toContain('/reorder');
});
