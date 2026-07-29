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

it('serializes concurrent code verification so only one reset token is issued', function () {
    $user = User::query()->sole();
    $workflow = PasswordReset::factory()->forUser($user)->create([
        'email_normalized' => $user->email,
        'code_hash' => Hash::make('654321'),
        'code_expires_at' => now()->addMinutes(10),
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
            'verify',
            'admin@example.test',
            '654321',
            '',
            '203.0.113.51',
        ]);
        $second = new Process([
            PHP_BINARY,
            base_path('tests/Support/PasswordRecoveryConcurrencyRunner.php'),
            'verify',
            'admin@example.test',
            '654321',
            '',
            '203.0.113.51',
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
            ->and($statuses)->toContain('invalid');

        $workflow->refresh();

        expect($workflow->verified_at)->not->toBeNull()
            ->and($workflow->reset_token_hash)->not->toBeNull()
            ->and(PasswordReset::query()->whereNotNull('reset_token_hash')->count())->toBe(1);
    } finally {
        if ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }
});
