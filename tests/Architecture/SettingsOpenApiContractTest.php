<?php

declare(strict_types=1);

function settingsOpenApiDocument(): string
{
    $document = file_get_contents(base_path('specs/006-settings-management/contracts/openapi.yaml'));

    expect($document)->toBeString()->not->toBeEmpty();

    return $document;
}

function settingsOpenApiSchemaBlock(string $document, string $schema): string
{
    preg_match(
        '/^    '.preg_quote($schema, '/').":\R(?<block>(?:(?!^    [A-Za-z][A-Za-z0-9]*:).*(?:\R|$))*)/m",
        $document,
        $matches,
    );

    expect($matches)->toHaveKey('block');

    return (string) $matches['block'];
}

function settingsOpenApiTopLevelProperties(string $schemaBlock): array
{
    preg_match('/^      properties:\R(?<properties>(?:^        .*(?:\R|$)|^          .*(?:\R|$)|^            .*(?:\R|$)|^              .*(?:\R|$))*)/m', $schemaBlock, $matches);
    preg_match_all('/^        ([A-Za-z][A-Za-z0-9]*):/m', (string) ($matches['properties'] ?? ''), $properties);

    return $properties[1] ?? [];
}

function settingsOpenApiRequiredKeys(string $schemaBlock): array
{
    preg_match('/^      required:\R(?<required>(?:^      - [A-Za-z][A-Za-z0-9]*\R?)*)/m', $schemaBlock, $matches);
    preg_match_all('/^      - ([A-Za-z][A-Za-z0-9]*)/m', (string) ($matches['required'] ?? ''), $required);

    return $required[1] ?? [];
}

it('keeps the settings contract valid and strictly OpenAPI 3.1', function () {
    $document = settingsOpenApiDocument();

    expect($document)->toStartWith("openapi: 3.1.0\n")
        ->and($document)->not->toContain("\t")
        ->and($document)->not->toMatch('/^\s*nullable:/m');

    preg_match_all('/^      operationId: ([A-Za-z][A-Za-z0-9]*)$/m', $document, $operationIds);

    expect($operationIds[1])->toHaveCount(3)
        ->and(array_unique($operationIds[1]))->toHaveCount(3)
        ->and($operationIds[1])->toBe([
            'adminGetSettings',
            'adminUpdateSettings',
            'publicGetSettings',
        ]);

    expect($document)->toMatch('/^  \/public\/settings:\R    get:.*?^      security: \[\]$/ms');
});

it('resolves every local settings OpenAPI reference', function () {
    $document = settingsOpenApiDocument();
    preg_match_all('/\$ref: \'#\/components\/(schemas|responses|securitySchemes)\/([A-Za-z][A-Za-z0-9]*)\'/', $document, $references, PREG_SET_ORDER);

    expect($references)->not->toBeEmpty();

    foreach ($references as $reference) {
        [$fullReference, $section, $name] = $reference;
        expect($document, "Unresolved local reference {$fullReference}")
            ->toMatch('/^    '.preg_quote($name, '/').':$/m');

        expect($section)->toBeIn(['schemas', 'responses', 'securitySchemes']);
    }
});

it('freezes exact request and response settings objects', function () {
    $document = settingsOpenApiDocument();
    $expectedProperties = [
        'PhoneInput' => ['number', 'hasWhats'],
        'PhoneOutput' => ['number', 'hasWhats'],
        'SocialLinkItem' => ['platform', 'url'],
        'AdminSettingsData' => [
            'siteNameAr', 'siteNameEn', 'siteDescriptionAr', 'siteDescriptionEn',
            'sloganAr', 'sloganEn', 'logo', 'footerLogo', 'favicon', 'publicEmail',
            'phones', 'addressAr', 'addressEn', 'googleMapsUrl', 'socialLinks', 'availableSocialPlatforms',
        ],
        'PublicSettingsData' => [
            'siteName', 'siteDescription', 'slogan', 'logo', 'footerLogo', 'favicon',
            'email', 'phones', 'address', 'googleMapsUrl', 'socialLinks',
        ],
        'AdminSettingsSuccessResponse' => ['success', 'message', 'data'],
        'PublicSettingsSuccessResponse' => ['success', 'message', 'data'],
        'ErrorResponse' => ['success', 'message', 'code', 'errors'],
        'ValidationErrorResponse' => ['success', 'message', 'code', 'errors'],
        'AdminSettingsUpdateRequest' => [
            'siteNameAr', 'siteNameEn', 'siteDescriptionAr', 'siteDescriptionEn',
            'sloganAr', 'sloganEn', 'publicEmail', 'addressAr', 'addressEn', 'googleMapsUrl',
            'phones', 'socialLinks', 'logo', 'footerLogo', 'favicon',
        ],
    ];

    foreach ($expectedProperties as $schema => $properties) {
        $block = settingsOpenApiSchemaBlock($document, $schema);
        expect($block, "{$schema} must reject undocumented properties")
            ->toMatch('/^      additionalProperties: false$/m');
        expect(settingsOpenApiTopLevelProperties($block))->toBe($properties);
    }

    foreach (['PhoneInput', 'PhoneOutput', 'SocialLinkItem', 'AdminSettingsData', 'PublicSettingsData', 'AdminSettingsSuccessResponse', 'PublicSettingsSuccessResponse', 'ErrorResponse', 'ValidationErrorResponse'] as $schema) {
        $block = settingsOpenApiSchemaBlock($document, $schema);
        expect(settingsOpenApiRequiredKeys($block))->toBe($expectedProperties[$schema]);
    }
});

it('separates formatted phone input from canonical phone output and uses JSON Schema null unions', function () {
    $document = settingsOpenApiDocument();
    $update = settingsOpenApiSchemaBlock($document, 'AdminSettingsUpdateRequest');
    $admin = settingsOpenApiSchemaBlock($document, 'AdminSettingsData');
    $public = settingsOpenApiSchemaBlock($document, 'PublicSettingsData');

    expect($update)->toContain("\$ref: '#/components/schemas/PhoneInput'")
        ->and($admin)->toContain("\$ref: '#/components/schemas/PhoneOutput'")
        ->and($public)->toContain("\$ref: '#/components/schemas/PhoneOutput'")
        ->and(settingsOpenApiSchemaBlock($document, 'PhoneInput'))->toContain('- 0501234567', "- '+201012345678'", '- 00201012345678', '- (010) 12345678')
        ->and(settingsOpenApiSchemaBlock($document, 'PhoneOutput'))->toContain('pattern: ^[0-9]{10,11}$');

    foreach (['AdminSettingsData', 'PublicSettingsData', 'ErrorResponse'] as $schema) {
        expect(settingsOpenApiSchemaBlock($document, $schema))->toMatch("/type:\\R\s+- [^\\r\\n]+\\R\s+- 'null'/");
    }
});
