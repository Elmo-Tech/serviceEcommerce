<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('updates the profile name and preserves the current avatar when omitted or empty', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    Storage::fake('public');
    $user = User::query()->sole();
    Storage::disk('public')->put('avatars/admin/current.jpg', 'avatar');
    $user->forceFill([
        'avatar_disk' => 'public',
        'avatar_path' => 'avatars/admin/current.jpg',
    ])->save();

    $accessToken = (string) loginAdminForTests()->json('data.accessToken');

    $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->patchJson('/api/v1/admin/auth/profile', [
            'name' => 'Updated Super Admin',
        ], [
            'Accept-Language' => 'en',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Super Admin')
        ->assertJsonPath('data.avatar', Storage::disk('public')->url('avatars/admin/current.jpg'));

    $emptyAvatarResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->post('/api/v1/admin/auth/profile', [
            '_method' => 'PATCH',
            'avatar' => '',
        ], [
            'Accept-Language' => 'en',
        ]);

    $emptyAvatarResponse->assertOk()
        ->assertJsonPath('data.avatar', Storage::disk('public')->url('avatars/admin/current.jpg'));
});

it('replaces the avatar with a random stored file and clears the old file after commit', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    Storage::fake('public');
    $user = User::query()->sole();
    Storage::disk('public')->put('avatars/admin/current.jpg', 'avatar');
    $user->forceFill([
        'avatar_disk' => 'public',
        'avatar_path' => 'avatars/admin/current.jpg',
    ])->save();

    $accessToken = (string) loginAdminForTests()->json('data.accessToken');

    $response = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->post('/api/v1/admin/auth/profile', [
            '_method' => 'PATCH',
            'avatar' => UploadedFile::fake()->image('new-avatar.png'),
        ], [
            'Accept-Language' => 'en',
        ]);

    $response->assertOk();

    $updatedUser = User::query()->sole();

    expect($updatedUser->avatar_disk)->toBe('public')
        ->and($updatedUser->avatar_path)->not->toBe('avatars/admin/current.jpg')
        ->and(Storage::disk('public')->exists($updatedUser->avatar_path))->toBeTrue()
        ->and(Storage::disk('public')->exists('avatars/admin/current.jpg'))->toBeFalse();
});

it('rejects forbidden profile fields and invalid avatar uploads', function () {
    seedAdminAuthEnvironment();
    $this->seed(SuperAdminSeeder::class);

    $accessToken = (string) loginAdminForTests()->json('data.accessToken');

    $forbiddenResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->patchJson('/api/v1/admin/auth/profile', [
            'email' => 'evil@example.test',
        ], [
            'Accept-Language' => 'en',
        ]);

    $forbiddenResponse->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    $invalidAvatarResponse = $this->withHeader('Authorization', 'Bearer '.$accessToken)
        ->post('/api/v1/admin/auth/profile', [
            '_method' => 'PATCH',
            'avatar' => 'https://example.com/avatar.png',
        ], [
            'Accept-Language' => 'en',
        ]);

    $invalidAvatarResponse->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});
