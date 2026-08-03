<?php

declare(strict_types=1);

function heroOpenApi(): string
{
    $document = file_get_contents(base_path('specs/008-hero-slider-management/contracts/openapi.yaml'));
    expect($document)->toBeString()->not->toBeEmpty();

    return $document;
}

function heroSchemaBlock(string $document, string $schema): string
{
    preg_match('/^    '.preg_quote($schema, '/').":\R(?<block>(?:(?!^    [A-Za-z][A-Za-z0-9]*:).*(?:\R|$))*)/m", $document, $matches);
    expect($matches)->toHaveKey('block');

    return (string) $matches['block'];
}

function heroSchemaProperties(string $block): array
{
    preg_match('/^      properties:\R(?<properties>(?:^        .*(?:\R|$)|^          .*(?:\R|$)|^            .*(?:\R|$)|^              .*(?:\R|$))*)/m', $block, $matches);
    preg_match_all('/^        ([A-Za-z][A-Za-z0-9]*):/m', (string) ($matches['properties'] ?? ''), $properties);

    return $properties[1] ?? [];
}

it('is strict OpenAPI 3.1 with six unique operations and resolved local references', function () {
    $document = heroOpenApi();
    expect($document)->toStartWith("openapi: 3.1.0\n")->not->toContain("\t", '/reorder');

    preg_match_all('/^      operationId: ([A-Za-z][A-Za-z0-9]*)$/m', $document, $operations);
    expect($operations[1])->toBe([
        'adminListHeroSlides', 'adminCreateHeroSlide', 'adminShowHeroSlide',
        'adminUpdateHeroSlide', 'adminDeleteHeroSlide', 'publicListHeroSlides',
    ])->and(array_unique($operations[1]))->toHaveCount(6);

    preg_match_all('/\$ref: \'#\/components\/(schemas|responses|parameters|headers|securitySchemes)\/([A-Za-z][A-Za-z0-9]*)\'/', $document, $refs, PREG_SET_ORDER);
    foreach ($refs as $ref) {
        expect($document, 'Unresolved '.$ref[0])->toMatch('/^    '.preg_quote($ref[2], '/').':$/m');
    }
});

it('freezes exact admin and public fields and multipart request allow lists', function () {
    $document = heroOpenApi();
    $expected = [
        'CreateHeroSlideRequest' => ['titleAr', 'titleEn', 'descriptionAr', 'descriptionEn', 'image', 'isActive', 'position'],
        'UpdateHeroSlideRequest' => ['titleAr', 'titleEn', 'descriptionAr', 'descriptionEn', 'image', 'isActive', 'position'],
        'AdminHeroSlide' => ['id', 'titleAr', 'titleEn', 'descriptionAr', 'descriptionEn', 'image', 'isActive', 'position'],
        'PublicHeroSlide' => ['title', 'description', 'image'],
        'PaginationMeta' => ['currentPage', 'lastPage', 'perPage', 'total'],
    ];

    foreach ($expected as $schema => $properties) {
        $block = heroSchemaBlock($document, $schema);
        expect($block)->toMatch('/^      additionalProperties: false$/m')
            ->and(heroSchemaProperties($block))->toBe($properties);
    }

    expect(substr_count($document, 'multipart/form-data:'))->toBe(2)
        ->and(heroSchemaBlock($document, 'CreateHeroSlideRequest'))->toContain('x-maxSize: 5242880', "enum:\n          - '0'\n          - '1'")
        ->and(heroSchemaBlock($document, 'UpdateHeroSlideRequest'))->toContain('minProperties: 1');
});

it('freezes permissions query inputs public headers statuses and stable error codes', function () {
    $document = heroOpenApi();
    preg_match_all('/^      x-required-permission: ([a-z.-]+)$/m', $document, $permissions);
    expect($permissions[1])->toBe([
        'hero-slides.view', 'hero-slides.create', 'hero-slides.view',
        'hero-slides.update', 'hero-slides.delete',
    ])
        ->and($document)->toContain(
            'name: page', 'name: perPage', 'name: filter[isActive]',
            'maximum: 100', 'const: Accept-Language', 'Content-Language:',
            "'201':", "'401':", "'403':", "'404':", "'422':", "'500':",
            'HERO_SLIDE_NOT_FOUND', 'VALIDATION_ERROR',
        )
        ->and($document)->toMatch('/^  \/public\/hero-slides:\R    get:.*?^      security: \[\]$/ms');
});
