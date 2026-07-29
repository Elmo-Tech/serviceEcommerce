<?php

declare(strict_types=1);

use App\Actions\Auth\RefreshAdminSessionAction;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$plainTextToken = $argv[1] ?? null;
$ipAddress = $argv[2] ?? null;

if (! is_string($plainTextToken) || $plainTextToken === '') {
    fwrite(STDERR, 'Missing refresh token.');

    exit(1);
}

$result = $app->make(RefreshAdminSessionAction::class)->execute($plainTextToken, is_string($ipAddress) ? $ipAddress : null);

echo json_encode($result, JSON_THROW_ON_ERROR);
