<?php

declare(strict_types=1);

use App\Enums\HttpStatusCode;
use App\Mail\AdminPasswordResetCodeMail;
use App\Models\PasswordReset;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('creates a hashed recovery workflow and sends the localized mail synchronously', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    Mail::fake();
    $sentCode = null;

    $response = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'ADMIN@EXAMPLE.TEST',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', trans('auth.forgot_password_accepted', [], 'en'))
        ->assertJsonPath('data', []);

    Mail::assertSent(AdminPasswordResetCodeMail::class, function (AdminPasswordResetCodeMail $mail) use (&$sentCode) {
        $sentCode = $mail->code;

        return $mail->code !== '' && $mail->expiresInMinutes === 10;
    });

    $workflow = PasswordReset::query()->sole();

    expect($workflow->email_normalized)->toBe('admin@example.test')
        ->and($sentCode)->not->toBeNull()
        ->and(Hash::check((string) $sentCode, $workflow->code_hash))->toBeTrue()
        ->and($workflow->code_expires_at)->not->toBeNull();
});

it('keeps unknown and inactive administrator requests generic and does not queue mail', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    Mail::fake();

    $unknown = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'unknown@example.test',
    ]);

    $user = User::query()->sole();
    $user->forceFill(['is_active' => false])->save();

    $inactive = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ]);

    $unknown->assertOk()
        ->assertJsonPath('data', []);
    $inactive->assertOk()
        ->assertJsonPath('data', []);
    Mail::assertNothingSent();
    expect(PasswordReset::query()->count())->toBe(0);
});

it('enforces forgot-password validation and exact payload boundaries', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $responses = [
        $this->postJson('/api/v1/admin/auth/forgot-password', [], [
            'Accept-Language' => 'en',
        ]),
        $this->postJson('/api/v1/admin/auth/forgot-password', [
            'email' => 'not-an-email',
        ], [
            'Accept-Language' => 'en',
        ]),
        $this->postJson('/api/v1/admin/auth/forgot-password', [
            'email' => 'admin@example.test',
            'unexpected' => true,
        ], [
            'Accept-Language' => 'en',
        ]),
    ];

    foreach ($responses as $response) {
        $response->assertStatus(HttpStatusCode::UNPROCESSABLE_ENTITY->value)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
});

it('rate limits resend attempts during the cooldown window without creating a second usable workflow', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    Mail::fake();

    $firstResponse = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    $secondResponse = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    $firstResponse->assertOk();
    $secondResponse->assertStatus(HttpStatusCode::TOO_MANY_REQUESTS->value)
        ->assertJsonPath('code', 'RATE_LIMITED');

    Mail::assertSent(AdminPasswordResetCodeMail::class, 1);

    expect(PasswordReset::query()->count())->toBe(1)
        ->and(PasswordReset::query()->sole()->consumed_at)->toBeNull();
});

it('returns the same enumeration-safe success contract for eligible unknown and inactive targets', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    Mail::fake();

    $eligible = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    PasswordReset::query()->delete();
    Mail::fake();

    $unknown = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'unknown@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    $user = User::query()->sole();
    $user->forceFill(['is_active' => false])->save();

    $inactive = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    expect($eligible->json())->toBe($unknown->json())
        ->and($unknown->json())->toBe($inactive->json());
});

it('invalidates only the newly created workflow when synchronous mail transport fails after commit', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    PasswordReset::factory()->create([
        'email_normalized' => 'admin@example.test',
        'consumed_at' => now()->subMinute(),
    ]);

    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andReturnUsing(function () {
        expect(PasswordReset::query()->count())->toBe(2)
            ->and(PasswordReset::query()->latest('id')->first()?->consumed_at)->toBeNull();

        throw new RuntimeException('smtp-down');
    });

    $response = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'en',
    ]);

    $response->assertStatus(HttpStatusCode::SERVICE_UNAVAILABLE->value)
        ->assertJsonPath('code', 'MAIL_SERVICE_UNAVAILABLE');

    $workflows = PasswordReset::query()->orderBy('id')->get();

    expect($workflows)->toHaveCount(2)
        ->and($workflows[0]->consumed_at)->not->toBeNull()
        ->and($workflows[1]->consumed_at)->not->toBeNull();
});

it('renders the recovery mail in the resolved locale and sends it synchronously rather than queueing it', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    Mail::fake();

    $response = $this->postJson('/api/v1/admin/auth/forgot-password', [
        'email' => 'admin@example.test',
    ], [
        'Accept-Language' => 'ar',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Language', 'ar');

    Mail::assertSent(AdminPasswordResetCodeMail::class, function (AdminPasswordResetCodeMail $mail) {
        app()->setLocale('ar');
        $rendered = $mail->render();

        return $mail->subject === trans('mail.password_reset_code_subject', [], 'ar')
            && str_contains($rendered, trans('mail.password_reset_code_intro', [], 'ar'))
            && str_contains($rendered, trans('mail.password_reset_code_expiry', ['minutes' => 10], 'ar'));
    });

    Mail::assertNothingQueued();
});
