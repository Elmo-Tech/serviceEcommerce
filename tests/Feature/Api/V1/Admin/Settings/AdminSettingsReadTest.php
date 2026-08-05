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

it('returns the complete admin settings resource with ordered children and available social platforms', function () {
    $setting = Setting::factory()->create([
        'id' => 1,
        'google_maps_url' => 'https://maps.google.com/?q=30.0444,31.2357',
    ]);

    $setting->phones()->createMany([
        ['number' => '01111111111', 'has_whats' => 0, 'position' => 1],
        ['number' => '01012345678', 'has_whats' => 1, 'position' => 0],
    ]);

    $setting->socialLinks()->createMany([
        ['platform' => SocialPlatform::INSTAGRAM, 'url' => 'https://instagram.com/example', 'position' => 1],
        ['platform' => SocialPlatform::FACEBOOK, 'url' => 'https://facebook.com/example', 'position' => 0],
    ]);

    $response = $this->getJson('/api/v1/admin/settings', settingsAdminHeaders(settingsAdminToken()));

    $response->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('success', true)
        ->assertJsonMissingPath('data.siteNameAr')
        ->assertJsonMissingPath('data.siteNameEn')
        ->assertJsonMissingPath('data.siteDescriptionAr')
        ->assertJsonMissingPath('data.siteDescriptionEn')
        ->assertJsonMissingPath('data.sloganAr')
        ->assertJsonMissingPath('data.sloganEn')
        ->assertJsonPath('data.publicEmail', 'info@example.com')
        ->assertJsonPath('data.phones.0.number', '01012345678')
        ->assertJsonPath('data.phones.0.hasWhats', 1)
        ->assertJsonPath('data.phones.1.number', '01111111111')
        ->assertJsonPath('data.socialLinks.0.platform', 'facebook')
        ->assertJsonPath('data.socialLinks.1.platform', 'instagram')
        ->assertJsonPath('data.googleMapsUrl', 'https://maps.google.com/?q=30.0444,31.2357')
        ->assertJsonPath('data.availableSocialPlatforms', SocialPlatform::keys())
        ->assertJsonMissingPath('data.latitude')
        ->assertJsonMissingPath('data.longitude')
        ->assertJsonMissingPath('data.defaultSeoTitleAr')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.createdAt')
        ->assertJsonMissingPath('data.updatedAt');
});

it('recovers the missing singleton row on admin read without creating duplicates', function () {
    expect(Setting::query()->count())->toBe(0);

    $firstResponse = $this->getJson('/api/v1/admin/settings', settingsAdminHeaders(settingsAdminToken()));
    $secondResponse = $this->getJson('/api/v1/admin/settings', settingsAdminHeaders(settingsAdminToken()));

    $firstResponse->assertOk()
        ->assertJsonPath('data.publicEmail', 'info@example.com');

    $secondResponse->assertOk();

    expect(Setting::query()->count())->toBe(1)
        ->and(Setting::query()->sole()->getKey())->toBe(1);
});

it('returns the approved authentication and permission boundaries for admin settings read', function () {
    $this->getJson('/api/v1/admin/settings', [
        'Accept-Language' => 'en',
    ])->assertUnauthorized()
        ->assertJsonPath('code', 'UNAUTHENTICATED');

    $this->seed(RolesAndPermissionsSeeder::class);

    $inactiveAdmin = User::factory()->administrator()->inactive()->create([
        'email' => 'inactive-settings-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    Sanctum::actingAs($inactiveAdmin);

    $this->getJson('/api/v1/admin/settings', [
        'Accept-Language' => 'en',
    ])->assertForbidden()
        ->assertJsonPath('code', 'USER_INACTIVE');

    $forbiddenAdmin = User::factory()->administrator()->create([
        'email' => 'forbidden-settings-admin@example.test',
        'password' => Hash::make('Password123!'),
    ]);

    Sanctum::actingAs($forbiddenAdmin);

    $this->getJson('/api/v1/admin/settings', [
        'Accept-Language' => 'en',
    ])->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
