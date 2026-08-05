<?php

declare(strict_types=1);

use App\Actions\Settings\UpdateSettingsAction;
use App\Services\Settings\SettingsResolver;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? null;

if (! is_string($mode) || $mode === '') {
    fwrite(STDERR, 'Missing settings concurrency mode.');

    exit(1);
}

try {
    switch ($mode) {
        case 'resolve-admin':
            $setting = $app->make(SettingsResolver::class)->resolveForAdmin(lockForUpdate: true);

            echo json_encode([
                'status' => 'success',
                'settingId' => $setting->getKey(),
            ], JSON_THROW_ON_ERROR);

            exit(0);

        case 'update-settings':
            $payload = json_decode($argv[2] ?? '', true, 512, JSON_THROW_ON_ERROR);
            $setting = $app->make(UpdateSettingsAction::class)->execute($payload);

            echo json_encode([
                'status' => 'success',
                'settingId' => $setting->getKey(),
                'addressEn' => $setting->address_en,
            ], JSON_THROW_ON_ERROR);

            exit(0);
    }

    fwrite(STDERR, 'Unknown settings concurrency mode.');

    exit(1);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable::class.': '.$throwable->getMessage());

    exit(1);
}
