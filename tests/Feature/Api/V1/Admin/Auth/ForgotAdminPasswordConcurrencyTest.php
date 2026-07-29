<?php

declare(strict_types=1);

use App\Models\PasswordReset;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    putenv('MAIL_MAILER=log');
    putenv('MAIL_LOG_CHANNEL=single');
    $_ENV['MAIL_MAILER'] = 'log';
    $_ENV['MAIL_LOG_CHANNEL'] = 'single';
    $_SERVER['MAIL_MAILER'] = 'log';
    $_SERVER['MAIL_LOG_CHANNEL'] = 'single';

    File::ensureDirectoryExists(storage_path('logs'));
    File::put(storage_path('logs/laravel.log'), '');

    Artisan::call('migrate:fresh', ['--force' => true]);
    $this->seed(SuperAdminSeeder::class);
});

afterEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
});

it('serializes concurrent first-time forgot-password requests so only one workflow and mail are produced', function () {
    $connection = DB::connection();
    $connection->beginTransaction();

    try {
        $connection->table('users')
            ->where('email', 'admin@example.test')
            ->lockForUpdate()
            ->first();

        $first = new Process([
            PHP_BINARY,
            base_path('tests/Support/PasswordRecoveryConcurrencyRunner.php'),
            'forgot',
            'admin@example.test',
            '',
            '',
            '203.0.113.50',
        ]);
        $second = new Process([
            PHP_BINARY,
            base_path('tests/Support/PasswordRecoveryConcurrencyRunner.php'),
            'forgot',
            'admin@example.test',
            '',
            '',
            '203.0.113.50',
        ]);

        $first->setTimeout(20);
        $second->setTimeout(20);
        $first->start();
        $second->start();

        usleep(500000);

        expect($first->isRunning())->toBeTrue()
            ->and($second->isRunning())->toBeTrue();

        $connection->commit();
        $first->wait();
        $second->wait();

        $firstResult = json_decode($first->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $secondResult = json_decode($second->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $statuses = [$firstResult['status'], $secondResult['status']];
        $logContents = File::get(storage_path('logs/laravel.log'));

        expect($statuses)->toContain('accepted')
            ->and($statuses)->toContain('rate_limited')
            ->and(PasswordReset::query()->count())->toBe(1)
            ->and(substr_count($logContents, 'To: admin@example.test'))->toBe(1);
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }
});
