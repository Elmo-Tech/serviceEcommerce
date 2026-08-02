<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\Settings\BrandingFileService;
use App\Services\Settings\SettingsResolver;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);
    Storage::fake('public');
    config()->set('filesystems.default', 'public');
});

function settingsSvg(string $name, string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $body);
}

function safeSettingsSvg(string $name = 'branding.svg'): UploadedFile
{
    return settingsSvg($name, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path fill="#123" d="M0 0h10v10H0z"/></svg>');
}

it('stores safe branding files and returns absolute URLs without exposing paths', function () {
    Setting::factory()->create(['id' => 1]);

    $response = $this->withHeaders(settingsAdminHeaders(settingsAdminToken()))->patch('/api/v1/admin/settings', [
        'logo' => UploadedFile::fake()->image('logo.jpg', 20, 20),
        'footerLogo' => UploadedFile::fake()->image('footer.png', 20, 20),
        'favicon' => safeSettingsSvg('favicon.svg'),
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonMissingPath('data.logoPath')
        ->assertJsonMissingPath('data.footerLogoPath')
        ->assertJsonMissingPath('data.faviconPath');

    foreach (['logo', 'footerLogo', 'favicon'] as $key) {
        expect((string) $response->json("data.{$key}"))->toStartWith('http');
    }

    $setting = Setting::query()->sole();
    Storage::disk('public')->assertExists((string) $setting->logo_path);
    Storage::disk('public')->assertExists((string) $setting->footer_logo_path);
    Storage::disk('public')->assertExists((string) $setting->favicon_path);
});

it('stores safe SVG content unchanged', function () {
    Setting::factory()->create(['id' => 1]);
    $content = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="4"/></svg>';

    $this->withHeaders(settingsAdminHeaders(settingsAdminToken()))->patch('/api/v1/admin/settings', [
        'logo' => settingsSvg('logo.svg', $content),
    ])->assertOk();

    $path = (string) Setting::query()->sole()->logo_path;
    expect(Storage::disk('public')->get($path))->toBe($content);
});

it('rejects invalid or unsafe SVG without storing it', function (string $content) {
    Setting::factory()->create(['id' => 1]);

    $this->withHeaders(settingsAdminHeaders(settingsAdminToken()))->patch('/api/v1/admin/settings', [
        'logo' => settingsSvg('logo.svg', $content),
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['errors' => ['logo']]);

    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(Setting::query()->sole()->logo_path)->toBeNull();
})->with([
    'invalid XML' => ['<svg><path></svg>'],
    'script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'javascript URL' => ['<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><path/></a></svg>'],
    'event handler' => ['<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(1)"><path/></svg>'],
    'foreign object' => ['<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>'],
    'iframe' => ['<svg xmlns="http://www.w3.org/2000/svg"><iframe src="https://example.com"/></svg>'],
    'object' => ['<svg xmlns="http://www.w3.org/2000/svg"><object data="https://example.com"/></svg>'],
    'embed' => ['<svg xmlns="http://www.w3.org/2000/svg"><embed src="https://example.com"/></svg>'],
    'external image' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/x.png"/></svg>'],
    'external CSS' => ['<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(https://example.com/x.css);</style></svg>'],
]);

it('rejects invalid file extensions and exact size boundaries', function () {
    Setting::factory()->create(['id' => 1]);
    $headers = settingsAdminHeaders(settingsAdminToken());

    $this->withHeaders($headers)->patch('/api/v1/admin/settings', [
        'logo' => UploadedFile::fake()->create('logo.gif', 10, 'image/gif'),
    ])->assertUnprocessable()->assertJsonStructure(['errors' => ['logo']]);

    $this->withHeaders($headers)->patch('/api/v1/admin/settings', [
        'favicon' => UploadedFile::fake()->create('favicon.png', 1025, 'image/png'),
    ])->assertUnprocessable()->assertJsonStructure(['errors' => ['favicon']]);

    $this->withHeaders($headers)->patch('/api/v1/admin/settings', [
        'logo' => UploadedFile::fake()->create('logo.png', 5121, 'image/png'),
    ])->assertUnprocessable()->assertJsonStructure(['errors' => ['logo']]);
});

it('rejects client paths and file-remove conflicts', function () {
    Setting::factory()->create(['id' => 1]);
    $headers = settingsAdminHeaders(settingsAdminToken());

    $this->patchJson('/api/v1/admin/settings', [
        'logoPath' => 'settings/logo/client-controlled.svg',
    ], $headers)->assertUnprocessable()
        ->assertJsonStructure(['errors' => ['payload']]);

    $this->withHeaders($headers)->patch('/api/v1/admin/settings', [
        'logo' => safeSettingsSvg('logo.svg'),
        'removeLogo' => 1,
    ])->assertUnprocessable()->assertJsonStructure(['errors' => ['logo']]);
});

it('replaces and removes branding only after valid state is committed', function () {
    Storage::disk('public')->put('settings/logo/old.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $setting = Setting::factory()->create(['id' => 1, 'logo_path' => 'settings/logo/old.svg']);
    $headers = settingsAdminHeaders(settingsAdminToken());

    $this->withHeaders($headers)->patch('/api/v1/admin/settings', [
        'logo' => safeSettingsSvg('new.svg'),
    ])->assertOk();

    $newPath = (string) $setting->fresh()->logo_path;
    expect($newPath)->not->toBe('settings/logo/old.svg');
    Storage::disk('public')->assertMissing('settings/logo/old.svg');
    Storage::disk('public')->assertExists($newPath);

    $this->patchJson('/api/v1/admin/settings', ['removeLogo' => 1], $headers)
        ->assertOk()->assertJsonPath('data.logo', null);
    Storage::disk('public')->assertMissing($newPath);
});

it('removes newly stored files when the database workflow fails', function () {
    Setting::factory()->create(['id' => 1]);
    $resolver = Mockery::mock(SettingsResolver::class);
    $resolver->shouldReceive('resolveForAdmin')->once()->andThrow(new RuntimeException('forced rollback'));
    app()->instance(SettingsResolver::class, $resolver);

    $this->withHeaders(settingsAdminHeaders(settingsAdminToken()))->patch('/api/v1/admin/settings', [
        'logo' => safeSettingsSvg('logo.svg'),
    ])->assertServerError();

    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and(Setting::query()->sole()->logo_path)->toBeNull();
});

it('logs post-commit cleanup failures without reverting the new database path', function () {
    $setting = Setting::factory()->create(['id' => 1, 'logo_path' => 'settings/logo/old.svg']);
    $service = Mockery::mock(BrandingFileService::class);
    $service->shouldReceive('store')->once()->andReturn([
        'disk' => 'public',
        'path' => 'settings/logo/new.svg',
    ]);
    $service->shouldReceive('delete')->once()->with('settings/logo/old.svg')
        ->andThrow(new RuntimeException('cleanup failed'));
    app()->instance(BrandingFileService::class, $service);
    Log::spy();

    $this->withHeaders(settingsAdminHeaders(settingsAdminToken()))->patch('/api/v1/admin/settings', [
        'logo' => safeSettingsSvg('logo.svg'),
    ])->assertOk();

    expect($setting->fresh()->logo_path)->toBe('settings/logo/new.svg');
    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'settings.branding_cleanup_failed'
            && $context['path'] === 'settings/logo/old.svg',
    );
});
