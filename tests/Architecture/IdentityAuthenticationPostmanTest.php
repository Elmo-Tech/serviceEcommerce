<?php

declare(strict_types=1);

it('ships exactly nine canonical identity-authentication postman operations with placeholder-only runtime values', function () {
    $collection = json_decode(
        file_get_contents(base_path('postman/Service-Commerce.postman_collection.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $collectionJson = file_get_contents(base_path('postman/Service-Commerce.postman_collection.json'));

    $environment = json_decode(
        file_get_contents(base_path('postman/Service-Commerce.local.postman_environment.json.example')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $topLevelItems = $collection['item'] ?? [];
    $folder = $topLevelItems[0] ?? [];
    $operations = $folder['item'] ?? [];
    $operationNames = array_map(
        static fn (array $operation): string => (string) ($operation['name'] ?? ''),
        $operations,
    );

    $environmentValues = collect($environment['values'] ?? [])
        ->mapWithKeys(static fn (array $value): array => [
            (string) ($value['key'] ?? '') => $value['value'] ?? null,
        ]);

    $collectionVariables = collect($collection['variable'] ?? [])
        ->mapWithKeys(static fn (array $value): array => [
            (string) ($value['key'] ?? '') => $value['value'] ?? null,
        ]);

    $refreshOperation = collect($operations)
        ->firstWhere('name', 'Refresh');

    $verifyCodeOperation = collect($operations)
        ->firstWhere('name', 'Verify Forgot Password Code');

    expect($topLevelItems)->toHaveCount(1)
        ->and((string) ($folder['name'] ?? ''))->toBe('Admin Auth')
        ->and($operations)->toHaveCount(9)
        ->and($operationNames)->toBe([
            'Login',
            'Refresh',
            'Logout',
            'Show Profile',
            'Update Profile',
            'Change Password',
            'Forgot Password',
            'Verify Forgot Password Code',
            'Reset Password',
        ])
        ->and($collectionJson)->toContain('{{baseUrl}}/admin/auth/login')
        ->and($collectionJson)->not->toContain('{{baseUrl}}/api/v1/')
        ->and($collectionJson)->toContain('"key": "refreshToken"')
        ->and($collectionJson)->toContain('"refreshToken"')
        ->and($collectionJson)->not->toContain('X-CSRF-TOKEN')
        ->and($environmentValues->all())->toMatchArray([
            'baseUrl' => 'https://api.backend-example.net/api/v1',
            'accessToken' => '',
            'refreshToken' => '',
        ])
        ->and($collectionVariables->all())->toMatchArray([
            'baseUrl' => 'https://api.backend-example.net/api/v1',
            'accessToken' => '',
            'refreshToken' => '',
        ])
        ->and(collect($refreshOperation['request']['header'] ?? [])->pluck('key')->all())->not->toContain('Origin', 'X-CSRF-TOKEN')
        ->and(json_encode($refreshOperation, JSON_THROW_ON_ERROR))->toContain('{{refreshToken}}')
        ->and(json_encode($verifyCodeOperation, JSON_THROW_ON_ERROR))->toContain('<set-locally>');
});
