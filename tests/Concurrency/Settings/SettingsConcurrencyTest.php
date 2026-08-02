<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
});

afterEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
});

function startSettingsConcurrencyProcess(string ...$arguments): Process
{
    $process = new Process([
        PHP_BINARY,
        base_path('tests/Support/SettingsConcurrencyRunner.php'),
        ...$arguments,
    ]);

    $process->setTimeout(20);
    $process->start();

    return $process;
}

function waitForSettingsConcurrencyProcess(Process $process): array
{
    $process->wait();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Settings concurrency runner failed.');
    }

    return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
}

it('recovers a missing settings singleton concurrently without duplicate-key leakage', function () {
    expect(Setting::query()->count())->toBe(0);

    $firstProcess = startSettingsConcurrencyProcess('resolve-admin');
    $secondProcess = startSettingsConcurrencyProcess('resolve-admin');

    $results = [
        waitForSettingsConcurrencyProcess($firstProcess),
        waitForSettingsConcurrencyProcess($secondProcess),
    ];

    expect(Setting::query()->count())->toBe(1)
        ->and(Setting::query()->sole()->getKey())->toBe(1)
        ->and(collect($results)->pluck('status')->unique()->all())->toBe(['success'])
        ->and(collect($results)->pluck('settingId')->unique()->all())->toBe([1]);
});

it('serializes concurrent full replacements without partial child collections', function () {
    Setting::factory()->create(['id' => 1]);

    $firstPayload = json_encode([
        'siteNameEn' => 'First concurrent update',
        'phones' => [
            ['number' => '01011111111', 'hasWhats' => 1],
            ['number' => '01111111111', 'hasWhats' => 0],
            ['number' => '01211111111', 'hasWhats' => 0],
        ],
        'socialLinks' => [
            ['platform' => 'facebook', 'url' => 'https://facebook.com/first'],
            ['platform' => 'instagram', 'url' => 'https://instagram.com/first'],
        ],
    ], JSON_THROW_ON_ERROR);

    $secondPayload = json_encode([
        'siteNameEn' => 'Second concurrent update',
        'phones' => [
            ['number' => '01522222222', 'hasWhats' => 0],
            ['number' => '01022222222', 'hasWhats' => 1],
        ],
        'socialLinks' => [
            ['platform' => 'youtube', 'url' => 'https://youtube.com/@second'],
            ['platform' => 'linkedin', 'url' => 'https://linkedin.com/company/second'],
        ],
    ], JSON_THROW_ON_ERROR);

    $firstProcess = startSettingsConcurrencyProcess('update-settings', $firstPayload);
    $secondProcess = startSettingsConcurrencyProcess('update-settings', $secondPayload);

    $results = [
        waitForSettingsConcurrencyProcess($firstProcess),
        waitForSettingsConcurrencyProcess($secondProcess),
    ];

    $setting = Setting::query()->with(['phones', 'socialLinks'])->sole();
    $phoneSets = [
        ['01011111111', '01111111111', '01211111111'],
        ['01522222222', '01022222222'],
    ];
    $socialSets = [
        ['facebook', 'instagram'],
        ['youtube', 'linkedin'],
    ];

    expect(collect($results)->pluck('status')->unique()->all())->toBe(['success'])
        ->and($setting->phones->count())->toBeLessThanOrEqual(3)
        ->and($setting->phones->where('has_whats', 1))->toHaveCount(1)
        ->and($setting->phones->pluck('number')->unique())->toHaveCount($setting->phones->count())
        ->and($setting->socialLinks->pluck('platform')->unique())->toHaveCount($setting->socialLinks->count())
        ->and($setting->phones->pluck('number')->all())->toBeIn($phoneSets)
        ->and($setting->socialLinks->map(fn ($link): string => $link->platform->key())->all())->toBeIn($socialSets);
});
