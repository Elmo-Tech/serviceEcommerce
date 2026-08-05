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
        'address_en' => 'Custom Address',
        'public_email' => 'custom@example.com',
    ]);

    $this->seed(SettingsSeeder::class);

    $reloaded = Setting::query()->sole();

    expect(Setting::query()->count())->toBe(1)
        ->and($reloaded->address_en)->toBe('Custom Address')
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
        ->and($publicDefaults)->not->toHaveKey('siteName')
        ->and($publicDefaults['email'])->toBe('info@example.com')
        ->and(Setting::query()->count())->toBe(0);
});
