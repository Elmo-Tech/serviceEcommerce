<?php

declare(strict_types=1);

use App\Models\RefreshToken;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    Artisan::call('migrate:fresh', ['--force' => true]);
    $this->seed(SuperAdminSeeder::class);
});

afterEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
});

it('serializes concurrent refresh rotations so one predecessor yields one replacement pair', function () {
    $loginResponse = loginAdminForTests();
    $refreshToken = (string) $loginResponse->json('data.refreshToken');
    $tokenHash = hash('sha256', $refreshToken);

    $connection = DB::connection();
    $connection->beginTransaction();

    try {
        $connection->table('refresh_tokens')
            ->where('token_hash', $tokenHash)
            ->lockForUpdate()
            ->first();

        $process = new Process([
            PHP_BINARY,
            base_path('tests/Support/RefreshConcurrencyRunner.php'),
            $refreshToken,
            '203.0.113.40',
        ]);
        $process->setTimeout(20);
        $process->start();

        usleep(500000);

        expect($process->isRunning())->toBeTrue();

        $connection->commit();
        $process->wait();

        $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        expect($result['status'])->toBe('success')
            ->and($result['refreshToken'])->not->toBe($refreshToken)
            ->and(User::query()->sole()->tokens()->count())->toBe(1)
            ->and(RefreshToken::query()->whereNull('revoked_at')->count())->toBe(1);
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }
});
