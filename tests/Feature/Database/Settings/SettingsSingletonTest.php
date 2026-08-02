<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Services\Settings\SettingsResolver;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the settings singleton idempotently without overwriting admin-managed values', function () {
    $this->seed(SettingsSeeder::class);

    $setting = Setting::query()->sole();
    $setting->update([
        'site_name_en' => 'Custom Website Name',
        'public_email' => 'custom@example.com',
    ]);

    $this->seed(SettingsSeeder::class);

    $reloaded = Setting::query()->sole();

    expect(Setting::query()->count())->toBe(1)
        ->and($reloaded->site_name_en)->toBe('Custom Website Name')
        ->and($reloaded->public_email)->toBe('custom@example.com');
});

it('resolves the admin singleton and public defaults according to the approved persistence rules', function () {
    /** @var SettingsResolver $resolver */
    $resolver = app(SettingsResolver::class);

    $adminSetting = $resolver->resolveForAdmin();

    expect($adminSetting->getKey())->toBe(1)
        ->and(Setting::query()->count())->toBe(1);

    Setting::query()->delete();

    $publicSetting = $resolver->resolveForPublic();
    $publicDefaults = $resolver->safePublicDefaults('en');

    expect($publicSetting)->toBeNull()
        ->and($publicDefaults['siteName'])->toBe('Website Name')
        ->and(Setting::query()->count())->toBe(0);
});
