<?php

declare(strict_types=1);

use App\Enums\Settings\SocialPlatform;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns localized public settings without admin-only fields', function () {
    $setting = Setting::factory()->create([
        'id' => 1,
        'site_name_ar' => 'اسم عربي',
        'site_name_en' => 'English Name',
        'address_ar' => 'القاهرة، مصر',
        'address_en' => 'Cairo, Egypt',
    ]);

    $setting->phones()->create(['number' => '01012345678', 'has_whats' => 1, 'position' => 0]);
    $setting->socialLinks()->create(['platform' => SocialPlatform::FACEBOOK, 'url' => 'https://facebook.com/example', 'position' => 0]);

    $arabicResponse = $this->getJson('/api/v1/public/settings', [
        'Accept-Language' => 'ar-EG',
    ]);

    $arabicResponse->assertOk()
        ->assertHeader('Content-Language', 'ar')
        ->assertHeader('Vary', 'Accept-Language')
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.siteName', 'اسم عربي')
        ->assertJsonPath('data.address', 'القاهرة، مصر')
        ->assertJsonPath('data.phones.0.number', '01012345678')
        ->assertJsonPath('data.socialLinks.0.platform', 'facebook')
        ->assertJsonMissingPath('data.googleMapsUrl')
        ->assertJsonMissingPath('data.latitude')
        ->assertJsonMissingPath('data.longitude')
        ->assertJsonMissingPath('data.defaultSeo')
        ->assertJsonMissingPath('data.siteNameAr')
        ->assertJsonMissingPath('data.siteNameEn')
        ->assertJsonMissingPath('data.availableSocialPlatforms');

    $englishResponse = $this->getJson('/api/v1/public/settings', [
        'Accept-Language' => 'en-US',
    ]);

    $englishResponse->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('data.siteName', 'English Name')
        ->assertJsonPath('data.address', 'Cairo, Egypt');
});

it('returns approved safe defaults for public settings without persisting a row', function () {
    expect(Setting::query()->count())->toBe(0);

    $response = $this->getJson('/api/v1/public/settings', [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.siteName', 'Website Name')
        ->assertJsonPath('data.email', 'info@example.com')
        ->assertJsonPath('data.phones', [])
        ->assertJsonPath('data.socialLinks', []);

    expect(Setting::query()->count())->toBe(0);
});
