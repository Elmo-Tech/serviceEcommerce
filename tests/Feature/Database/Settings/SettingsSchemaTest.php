<?php

declare(strict_types=1);

use App\Enums\Settings\SocialPlatform;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('provides the exact settings schema and model casts', function () {
    expect(Schema::hasColumns('settings', [
        'id', 'site_name_ar', 'site_name_en', 'site_description_ar', 'site_description_en',
        'slogan_ar', 'slogan_en', 'address_ar', 'address_en', 'public_email',
        'logo_path', 'footer_logo_path', 'favicon_path', 'google_maps_url',
        'latitude', 'longitude', 'default_seo_title_ar', 'default_seo_title_en',
        'default_seo_description_ar', 'default_seo_description_en',
        'default_seo_keywords_ar', 'default_seo_keywords_en', 'created_at', 'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('settings', 'logo_disk'))->toBeFalse()
        ->and(Schema::hasColumn('settings', 'footer_logo_disk'))->toBeFalse()
        ->and(Schema::hasColumn('settings', 'favicon_disk'))->toBeFalse();

    $setting = Setting::factory()->create([
        'id' => 1,
        'latitude' => '30.1234567',
        'longitude' => '31.7654321',
        'default_seo_keywords_ar' => ['خدمات', 'تصميم'],
        'default_seo_keywords_en' => ['Services', 'Design'],
    ]);

    expect($setting->incrementing)->toBeFalse()
        ->and($setting->getKey())->toBe(1)
        ->and($setting->latitude)->toBe('30.1234567')
        ->and($setting->longitude)->toBe('31.7654321')
        ->and($setting->default_seo_keywords_ar)->toBe(['خدمات', 'تصميم'])
        ->and($setting->default_seo_keywords_en)->toBe(['Services', 'Design']);
});

it('orders child relations and enforces their database uniqueness', function () {
    $setting = Setting::factory()->create(['id' => 1]);

    $setting->phones()->createMany([
        ['number' => '01112345678', 'has_whats' => 0, 'position' => 1],
        ['number' => '01012345678', 'has_whats' => 1, 'position' => 0],
    ]);
    $setting->socialLinks()->createMany([
        ['platform' => SocialPlatform::INSTAGRAM, 'url' => 'https://instagram.com/example', 'position' => 1],
        ['platform' => SocialPlatform::FACEBOOK, 'url' => 'https://facebook.com/example', 'position' => 0],
    ]);

    expect($setting->fresh()->phones->pluck('number')->all())->toBe(['01012345678', '01112345678'])
        ->and($setting->fresh()->socialLinks->pluck('platform')->all())->toBe([
            SocialPlatform::FACEBOOK,
            SocialPlatform::INSTAGRAM,
        ]);

    expect(fn () => $setting->phones()->create([
        'number' => '01012345678', 'has_whats' => 0, 'position' => 2,
    ]))->toThrow(QueryException::class);

    expect(fn () => $setting->socialLinks()->create([
        'platform' => SocialPlatform::FACEBOOK, 'url' => 'https://facebook.com/duplicate', 'position' => 2,
    ]))->toThrow(QueryException::class);
});

it('cascades child rows when the canonical settings row is deleted', function () {
    $setting = Setting::factory()->create(['id' => 1]);
    $setting->phones()->create(['number' => '01012345678', 'has_whats' => 1, 'position' => 0]);
    $setting->socialLinks()->create([
        'platform' => SocialPlatform::FACEBOOK,
        'url' => 'https://facebook.com/example',
        'position' => 0,
    ]);

    DB::table('settings')->where('id', 1)->delete();

    expect(DB::table('setting_phones')->count())->toBe(0)
        ->and(DB::table('setting_social_links')->count())->toBe(0);
});
