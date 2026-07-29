<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    setSeederEnvironment([
        'SUPER_ADMIN_NAME' => 'Service Commerce Super Admin',
        'SUPER_ADMIN_EMAIL' => 'Admin@Example.Test',
        'SUPER_ADMIN_PASSWORD' => 'AdminPassword1!',
        'SUPER_ADMIN_ADDITIONAL_USERS' => '',
    ]);
});

it('creates the super admin with normalized email, hashed password, admin type, active state, and role', function () {
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();

    expect($user->email)->toBe('admin@example.test')
        ->and(Hash::check('AdminPassword1!', $user->password))->toBeTrue()
        ->and($user->type)->toBe(UserType::ADMIN)
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasRole('super-admin'))->toBeTrue()
        ->and(Role::query()->where('name', 'super-admin')->count())->toBe(1);
});

it('is idempotent and preserves the existing password and identity on rerun', function () {
    $this->seed(SuperAdminSeeder::class);

    $originalUser = User::query()->sole();
    $originalPasswordHash = $originalUser->password;
    $originalName = $originalUser->name;

    setSeederEnvironment([
        'SUPER_ADMIN_NAME' => 'Changed Name',
        'SUPER_ADMIN_EMAIL' => ' admin@example.test ',
        'SUPER_ADMIN_PASSWORD' => 'DifferentPassword1!',
    ]);

    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->sole();

    expect(User::query()->count())->toBe(1)
        ->and(Role::query()->where('name', 'super-admin')->count())->toBe(1)
        ->and($user->name)->toBe($originalName)
        ->and($user->password)->toBe($originalPasswordHash)
        ->and(Hash::check('AdminPassword1!', $user->password))->toBeTrue()
        ->and($user->hasRole('super-admin'))->toBeTrue()
        ->and($user->isAdministrator())->toBeTrue()
        ->and($user->is_active)->toBeTrue();
});

it('fails safely when required seeding values are missing', function () {
    setSeederEnvironment([
        'SUPER_ADMIN_NAME' => '',
        'SUPER_ADMIN_EMAIL' => '',
        'SUPER_ADMIN_PASSWORD' => '',
    ]);

    expect(fn () => $this->seed(SuperAdminSeeder::class))
        ->toThrow(InvalidArgumentException::class, 'Super Admin seeding configuration is missing or invalid.');
});

it('fails safely when the configured password violates the approved policy', function () {
    setSeederEnvironment([
        'SUPER_ADMIN_NAME' => 'Service Commerce Super Admin',
        'SUPER_ADMIN_EMAIL' => 'admin@example.test',
        'SUPER_ADMIN_PASSWORD' => 'short',
    ]);

    expect(fn () => $this->seed(SuperAdminSeeder::class))
        ->toThrow(InvalidArgumentException::class, 'Super Admin seeding configuration is missing or invalid.');
});

it('preserves an existing matching identity while restoring admin flags and role assignment', function () {
    $existingUser = User::factory()->create([
        'name' => 'Existing Administrator',
        'email' => 'admin@example.test',
        'password' => Hash::make('ExistingPassword1!'),
        'type' => UserType::ADMIN,
        'is_active' => false,
    ]);

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    $user = $existingUser->fresh();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Existing Administrator')
        ->and(Hash::check('ExistingPassword1!', $user->password))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasRole('super-admin'))->toBeTrue();
});

it('seeds additional admin users from the configured list while preserving the shared password default', function () {
    setSeederEnvironment([
        'SUPER_ADMIN_NAME' => 'Service Commerce Super Admin',
        'SUPER_ADMIN_EMAIL' => 'Admin@Example.Test',
        'SUPER_ADMIN_PASSWORD' => 'AdminPassword1!',
        'SUPER_ADMIN_ADDITIONAL_USERS' => json_encode([
            [
                'name' => 'Ashraf Admin',
                'email' => 'ashrafmo-1@outlook.com',
            ],
        ], JSON_THROW_ON_ERROR),
    ]);

    $this->seed(SuperAdminSeeder::class);

    expect(User::query()->count())->toBe(2)
        ->and(User::query()->where('email', 'admin@example.test')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'ashrafmo-1@outlook.com')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'ashrafmo-1@outlook.com')->sole()->hasRole('super-admin'))->toBeTrue()
        ->and(Hash::check('AdminPassword1!', User::query()->where('email', 'ashrafmo-1@outlook.com')->sole()->password))->toBeTrue();
});

it('adds the repository-owned additional super admin during the full database seed flow', function () {
    $this->seed(DatabaseSeeder::class);

    $defaultAdmin = User::query()->where('email', 'admin@example.test')->first();
    $projectAdmin = User::query()->where('email', 'elmo@gmail.com')->first();

    expect($defaultAdmin)->not->toBeNull()
        ->and($projectAdmin)->not->toBeNull()
        ->and($projectAdmin?->name)->toBe('Elmo Super Admin')
        ->and($projectAdmin?->hasRole('super-admin'))->toBeTrue()
        ->and($projectAdmin?->type)->toBe(UserType::ADMIN)
        ->and($projectAdmin?->is_active)->toBeTrue()
        ->and(Hash::check('elmo123456', (string) $projectAdmin?->password))->toBeTrue();
});

function setSeederEnvironment(array $values): void
{
    foreach ($values as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
