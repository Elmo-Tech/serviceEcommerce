<?php

declare(strict_types=1);

use App\Enums\Settings\SocialPlatform;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
});

it('updates admin settings with normalized phones and ordered social links', function () {
    Setting::factory()->create(['id' => 1]);

    $response = $this->patchJson('/api/v1/admin/settings', [
        'siteNameAr' => '  الموقع الرسمي  ',
        'siteDescriptionAr' => '',
        'publicEmail' => ' INFO@example.com ',
        'latitude' => '30.1000000',
        'longitude' => '31.2000000',
        'phones' => [
            ['number' => '+20 101 234 5678', 'hasWhats' => 1],
            ['number' => '010-9999-8888', 'hasWhats' => 0],
        ],
        'socialLinks' => [
            ['platform' => 'instagram', 'url' => 'https://instagram.com/service-commerce'],
            ['platform' => 'facebook', 'url' => 'https://facebook.com/service-commerce'],
        ],
        'defaultSeoKeywordsAr' => [' خدمات ', 'تصميم'],
    ], settingsAdminHeaders(settingsAdminToken()));

    $response->assertOk()
        ->assertJsonPath('data.siteNameAr', 'الموقع الرسمي')
        ->assertJsonPath('data.siteDescriptionAr', null)
        ->assertJsonPath('data.publicEmail', 'info@example.com')
        ->assertJsonPath('data.latitude', '30.1000000')
        ->assertJsonPath('data.longitude', '31.2000000')
        ->assertJsonPath('data.phones.0.number', '01012345678')
        ->assertJsonPath('data.phones.0.hasWhats', 1)
        ->assertJsonPath('data.phones.1.number', '01099998888')
        ->assertJsonPath('data.socialLinks.0.platform', 'instagram')
        ->assertJsonPath('data.socialLinks.1.platform', 'facebook')
        ->assertJsonPath('data.defaultSeoKeywordsAr.0', 'خدمات')
        ->assertJsonPath('data.defaultSeoKeywordsAr.1', 'تصميم');

    $setting = Setting::query()->with(['phones', 'socialLinks'])->sole();

    expect($setting->public_email)->toBe('info@example.com')
        ->and($setting->phones->pluck('number')->all())->toBe(['01012345678', '01099998888'])
        ->and($setting->socialLinks->pluck('platform')->map(fn (SocialPlatform $platform): string => $platform->key())->all())
        ->toBe(['instagram', 'facebook']);
});

it('rejects duplicate normalized phones and duplicate social platforms during admin update', function () {
    Setting::factory()->create(['id' => 1]);

    $duplicatePhoneResponse = $this->patchJson('/api/v1/admin/settings', [
        'phones' => [
            ['number' => '+20 101 234 5678', 'hasWhats' => 1],
            ['number' => '01012345678', 'hasWhats' => 0],
        ],
    ], settingsAdminHeaders(settingsAdminToken()));

    $duplicatePhoneResponse->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['phones']]);

    $duplicatePlatformResponse = $this->patchJson('/api/v1/admin/settings', [
        'socialLinks' => [
            ['platform' => 'facebook', 'url' => 'https://facebook.com/one'],
            ['platform' => 'facebook', 'url' => 'https://facebook.com/two'],
        ],
    ], settingsAdminHeaders(settingsAdminToken()));

    $duplicatePlatformResponse->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['socialLinks']]);
});

it('clears phone and social collections when empty arrays are submitted', function () {
    $setting = Setting::factory()->create(['id' => 1]);
    $setting->phones()->create(['number' => '01012345678', 'has_whats' => 1, 'position' => 0]);
    $setting->socialLinks()->create(['platform' => SocialPlatform::FACEBOOK, 'url' => 'https://facebook.com/example', 'position' => 0]);

    $response = $this->patchJson('/api/v1/admin/settings', [
        'phones' => [],
        'socialLinks' => [],
    ], settingsAdminHeaders(settingsAdminToken()));

    $response->assertOk()
        ->assertJsonPath('data.phones', [])
        ->assertJsonPath('data.socialLinks', []);

    expect($setting->fresh()->phones()->count())->toBe(0)
        ->and($setting->fresh()->socialLinks()->count())->toBe(0);
});

it('accepts the real multipart patch shape sent by Postman', function () {
    Setting::factory()->create(['id' => 1]);
    $boundary = '----SettingsPostmanBoundary7MA4YWxk';
    $parts = [
        ['siteNameEn', 'Service Commerce'],
        ['publicEmail', 'info@example.com'],
        ['phones[0][number]', '+20 101 234 5678'],
        ['phones[0][hasWhats]', '1'],
    ];
    $body = '';

    foreach ($parts as [$name, $value]) {
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
        $body .= "{$value}\r\n";
    }

    $body .= "--{$boundary}--\r\n";

    $accessToken = settingsAdminToken();
    $response = $this->call(
        'PATCH',
        '/api/v1/admin/settings',
        server: [
            'CONTENT_TYPE' => "multipart/form-data; boundary={$boundary}",
            'CONTENT_LENGTH' => (string) strlen($body),
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
            'HTTP_AUTHORIZATION' => 'Bearer '.$accessToken,
        ],
        content: $body,
    );

    $response->assertOk()
        ->assertJsonPath('data.siteNameEn', 'Service Commerce')
        ->assertJsonPath('data.publicEmail', 'info@example.com')
        ->assertJsonPath('data.phones.0.number', '01012345678')
        ->assertJsonPath('data.phones.0.hasWhats', 1);
});

it('preserves omitted fields and explicitly clears optional scalar fields', function () {
    Setting::factory()->create([
        'id' => 1,
        'site_name_en' => 'Preserved Name',
        'site_description_en' => 'Clear me',
        'address_en' => 'Preserved Address',
    ]);

    $this->patchJson('/api/v1/admin/settings', [
        'siteDescriptionEn' => '   ',
        'sloganEn' => ' Updated slogan ',
    ], settingsAdminHeaders(settingsAdminToken()))
        ->assertOk()
        ->assertJsonPath('data.siteNameEn', 'Preserved Name')
        ->assertJsonPath('data.siteDescriptionEn', null)
        ->assertJsonPath('data.sloganEn', 'Updated slogan')
        ->assertJsonPath('data.addressEn', 'Preserved Address');
});

it('rejects empty required settings fields without changing persisted values', function (string $field, mixed $value) {
    $setting = Setting::factory()->create(['id' => 1]);
    $before = $setting->fresh()->getAttributes();

    $this->patchJson('/api/v1/admin/settings', [$field => $value], settingsAdminHeaders(settingsAdminToken()))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => [$field]]);

    expect($setting->fresh()->getAttributes())->toBe($before);
})->with([
    'Arabic site name empty' => ['siteNameAr', ''],
    'English site name null' => ['siteNameEn', null],
    'public email empty' => ['publicEmail', ''],
]);

it('accepts every approved Egyptian phone input and returns canonical output', function (string $input, string $expected) {
    Setting::factory()->create(['id' => 1]);

    $this->patchJson('/api/v1/admin/settings', [
        'phones' => [['number' => $input, 'hasWhats' => 0]],
    ], settingsAdminHeaders(settingsAdminToken()))
        ->assertOk()
        ->assertJsonPath('data.phones.0.number', $expected);
})->with([
    'local' => ['01012345678', '01012345678'],
    'plus 20' => ['+20 101 234 5678', '01012345678'],
    'double-zero 20' => ['00201012345678', '01012345678'],
    'spaces' => ['010 1234 5678', '01012345678'],
    'dashes' => ['010-1234-5678', '01012345678'],
    'parentheses' => ['(010) 12345678', '01012345678'],
]);

it('rejects phone and social collection limits and invariants', function (array $payload, string $errorKey) {
    Setting::factory()->create(['id' => 1]);

    $this->patchJson('/api/v1/admin/settings', $payload, settingsAdminHeaders(settingsAdminToken()))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => [$errorKey]]);
})->with([
    'four phones' => [[
        'phones' => [
            ['number' => '01011111111', 'hasWhats' => 0],
            ['number' => '01111111111', 'hasWhats' => 0],
            ['number' => '01211111111', 'hasWhats' => 0],
            ['number' => '01511111111', 'hasWhats' => 0],
        ],
    ], 'phones'],
    'two WhatsApp phones' => [[
        'phones' => [
            ['number' => '01011111111', 'hasWhats' => 1],
            ['number' => '01111111111', 'hasWhats' => 1],
        ],
    ], 'phones'],
    'invalid Egyptian phone' => [[
        'phones' => [['number' => '01912345678', 'hasWhats' => 0]],
    ], 'phones.0.number'],
    'ten social links' => [[
        'socialLinks' => array_map(
            fn (int $index): array => ['platform' => 'facebook', 'url' => "https://example.com/{$index}"],
            range(1, 10),
        ),
    ], 'socialLinks'],
]);

it('ignores removed collection clear and branding remove keys', function (string $key) {
    $setting = Setting::factory()->create(['id' => 1]);
    $before = $setting->fresh()->getAttributes();

    $this->patchJson('/api/v1/admin/settings', [$key => 1], settingsAdminHeaders(settingsAdminToken()))
        ->assertOk();

    expect($setting->fresh()->getAttributes())->toBe($before);
})->with([
    'clearPhones',
    'clearSocialLinks',
    'removeLogo',
    'removeFooterLogo',
    'removeFavicon',
]);

it('persists and clears coordinate pairs and rejects invalid coordinates', function () {
    Setting::factory()->create(['id' => 1]);
    $headers = settingsAdminHeaders(settingsAdminToken());

    $this->patchJson('/api/v1/admin/settings', [
        'latitude' => '30.0444000',
        'longitude' => '31.2357000',
        'googleMapsUrl' => 'https://maps.google.com/?q=30.0444,31.2357',
    ], $headers)->assertOk()
        ->assertJsonPath('data.latitude', '30.0444000')
        ->assertJsonPath('data.longitude', '31.2357000');

    $this->patchJson('/api/v1/admin/settings', ['latitude' => '30'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $this->patchJson('/api/v1/admin/settings', ['latitude' => '91', 'longitude' => '31'], $headers)
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['latitude']]);

    $this->patchJson('/api/v1/admin/settings', ['googleMapsUrl' => 'not-a-url'], $headers)
        ->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['googleMapsUrl']]);

    $this->patchJson('/api/v1/admin/settings', ['latitude' => '', 'longitude' => ''], $headers)
        ->assertOk()
        ->assertJsonPath('data.latitude', null)
        ->assertJsonPath('data.longitude', null);
});

it('trims SEO keywords while preserving casing and order and rejects empty or duplicate values', function () {
    Setting::factory()->create(['id' => 1]);
    $headers = settingsAdminHeaders(settingsAdminToken());

    $this->patchJson('/api/v1/admin/settings', [
        'defaultSeoKeywordsEn' => [' Service Commerce ', 'Web Design'],
    ], $headers)->assertOk()
        ->assertJsonPath('data.defaultSeoKeywordsEn', ['Service Commerce', 'Web Design']);

    $this->patchJson('/api/v1/admin/settings', [
        'defaultSeoKeywordsEn' => ['Service', ' service '],
    ], $headers)->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['defaultSeoKeywordsEn']]);

    $this->patchJson('/api/v1/admin/settings', [
        'defaultSeoKeywordsEn' => ['   '],
    ], $headers)->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

it('enforces authentication authorization and localized validation for settings update', function () {
    $this->patchJson('/api/v1/admin/settings', ['siteNameEn' => 'Updated'], [
        'Accept-Language' => 'en',
    ])->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');

    $arabicResponse = $this->patchJson('/api/v1/admin/settings', ['siteNameAr' => ''],
        settingsAdminHeaders(settingsAdminToken(), 'ar'));

    $arabicResponse->assertUnprocessable()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    expect((string) $arabicResponse->json('message'))->not->toBe('The submitted data is invalid.');

    $this->seed(RolesAndPermissionsSeeder::class);
    $forbiddenAdmin = User::factory()->administrator()->create([
        'email' => 'settings-update-forbidden@example.test',
        'password' => Hash::make('Password123!'),
    ]);
    Sanctum::actingAs($forbiddenAdmin);

    $this->patchJson('/api/v1/admin/settings', ['siteNameEn' => 'Updated'], [
        'Accept-Language' => 'en',
    ])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
});
