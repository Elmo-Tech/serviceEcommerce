<?php

declare(strict_types=1);

it('defines exactly the approved admin-auth openapi operations, paths, and reusable contracts', function () {
    $openApi = file_get_contents(base_path('specs/001-identity-authentication/contracts/openapi.yaml'));

    expect($openApi)->toBeString()
        ->and($openApi)->toContain('openapi: 3.1.0')
        ->and($openApi)->toContain('operationId: adminAuthLogin')
        ->and($openApi)->toContain('operationId: adminAuthRefresh')
        ->and($openApi)->toContain('operationId: adminAuthLogout')
        ->and($openApi)->toContain('operationId: adminAuthProfileShow')
        ->and($openApi)->toContain('operationId: adminAuthProfileUpdate')
        ->and($openApi)->toContain('operationId: adminAuthChangePassword')
        ->and($openApi)->toContain('operationId: adminAuthForgotPassword')
        ->and($openApi)->toContain('operationId: adminAuthVerifyForgotPasswordCode')
        ->and($openApi)->toContain('operationId: adminAuthResetPassword')
        ->and(substr_count($openApi, 'operationId:'))->toBe(9)
        ->and(substr_count($openApi, '  /admin/auth/'))->toBe(8)
        ->and($openApi)->toContain("  /admin/auth/profile:\n    get:")
        ->and($openApi)->toContain("\n    patch:\n")
        ->and($openApi)->not->toContain('/api/v1/auth/')
        ->and($openApi)->not->toContain('/api/v1/admin/login')
        ->and($openApi)->toContain('additionalProperties: false')
        ->and($openApi)->toContain('const: REFRESH_TOKEN_INVALID')
        ->and($openApi)->not->toContain('code: CSRF_TOKEN_MISMATCH')
        ->and($openApi)->not->toContain('code: ORIGIN_NOT_ALLOWED')
        ->and($openApi)->not->toContain('name: admin_refresh_token')
        ->and($openApi)->not->toContain('name: admin_csrf_token')
        ->and($openApi)->toContain('const: no-store, private')
        ->and($openApi)->toContain('const: no-cache');
});
