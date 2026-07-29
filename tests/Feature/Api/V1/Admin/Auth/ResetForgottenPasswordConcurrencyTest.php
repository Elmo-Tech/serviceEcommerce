<?php

declare(strict_types=1);

use App\Models\PasswordReset;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    seedAdminAuthEnvironment();
    Artisan::call('migrate:fresh', ['--force' => true]);
    $this->seed(SuperAdminSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
});

it('serializes concurrent password resets so only one request consumes the workflow', function () {
    $user = User::query()->sole();
    $workflow = PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
        'verified_at' => now(),
        'reset_token_hash' => hash('sha256', 'reset-token-value'),
        'reset_token_expires_at' => now()->addMinutes(10),
    ]);

    $connection = DB::connection();
    $connection->beginTransaction();

    try {
        $connection->table('password_resets')
            ->where('id', $workflow->getKey())
            ->lockForUpdate()
            ->first();

        $first = new Process([
            PHP_BINARY,
            base_path('tests/Support/PasswordRecoveryConcurrencyRunner.php'),
            'reset',
            'admin@example.test',
            'reset-token-value',
            'NewAdminPassword1!',
            '203.0.113.52',
        ]);
        $second = new Process([
            PHP_BINARY,
            base_path('tests/Support/PasswordRecoveryConcurrencyRunner.php'),
            'reset',
            'admin@example.test',
            'reset-token-value',
            'NewAdminPassword1!',
            '203.0.113.52',
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

        expect($statuses)->toContain('success')
            ->and($statuses)->toContain('invalid')
            ->and(Hash::check('NewAdminPassword1!', $user->fresh()->password))->toBeTrue();

        $workflow->refresh();

        expect($workflow->consumed_at)->not->toBeNull()
            ->and(PasswordReset::query()->whereNotNull('consumed_at')->count())->toBe(1);
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }
});
