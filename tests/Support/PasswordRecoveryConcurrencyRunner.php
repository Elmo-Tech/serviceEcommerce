<?php

declare(strict_types=1);

use App\Actions\Auth\ResetForgottenPasswordAction;
use App\Actions\Auth\SendForgotPasswordCodeAction;
use App\Actions\Auth\VerifyForgotPasswordCodeAction;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config()->set('mail.default', 'log');
config()->set('mail.mailers.log.channel', 'single');

$mode = $argv[1] ?? '';
$email = $argv[2] ?? null;
$payload = $argv[3] ?? null;
$payloadTwo = $argv[4] ?? null;
$ipAddress = $argv[5] ?? null;

if (! is_string($mode) || $mode === '' || ! is_string($email) || $email === '') {
    fwrite(STDERR, 'Missing concurrency runner arguments.');

    exit(1);
}

$result = match ($mode) {
    'forgot' => $app->make(SendForgotPasswordCodeAction::class)->execute($email, is_string($ipAddress) ? $ipAddress : null),
    'verify' => $app->make(VerifyForgotPasswordCodeAction::class)->execute(
        $email,
        is_string($payload) ? $payload : '',
        is_string($ipAddress) ? $ipAddress : null,
    ),
    'reset' => $app->make(ResetForgottenPasswordAction::class)->execute(
        $email,
        is_string($payload) ? $payload : '',
        is_string($payloadTwo) ? $payloadTwo : '',
        is_string($ipAddress) ? $ipAddress : null,
    ),
    default => null,
};

if (! is_array($result)) {
    fwrite(STDERR, 'Unsupported concurrency mode.');

    exit(1);
}

echo json_encode($result, JSON_THROW_ON_ERROR);
