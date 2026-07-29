<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Auth\AuthenticationSecurityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UpdateAdminProfileAction
{
    public function __construct(
        private readonly AuthenticationSecurityLogger $securityLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        User $user,
        array $attributes,
        ?UploadedFile $avatar = null,
        bool $preserveCurrentAvatar = true,
    ): User {
        $disk = (string) config('filesystems.default', 'public');
        $storedAvatarPath = null;
        $previousAvatarDisk = $user->avatar_disk;
        $previousAvatarPath = $user->avatar_path;

        if ($avatar instanceof UploadedFile) {
            $storedAvatarPath = $avatar->storeAs(
                'avatars/admin',
                Str::uuid()->toString().'.'.$avatar->extension(),
                $disk,
            );
        }

        try {
            $updatedUser = DB::transaction(function () use ($user, $attributes, $avatar, $preserveCurrentAvatar, $disk, $storedAvatarPath): User {
                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (array_key_exists('name', $attributes)) {
                    $lockedUser->name = trim((string) $attributes['name']);
                }

                if ($avatar instanceof UploadedFile && is_string($storedAvatarPath)) {
                    $lockedUser->avatar_disk = $disk;
                    $lockedUser->avatar_path = $storedAvatarPath;
                } elseif (! $preserveCurrentAvatar) {
                    $lockedUser->avatar_disk = $lockedUser->avatar_disk;
                    $lockedUser->avatar_path = $lockedUser->avatar_path;
                }

                $lockedUser->save();

                return $lockedUser->fresh(['roles', 'permissions']);
            });
        } catch (Throwable $throwable) {
            if (is_string($storedAvatarPath) && Storage::disk($disk)->exists($storedAvatarPath)) {
                Storage::disk($disk)->delete($storedAvatarPath);
            }

            throw $throwable;
        }

        if (
            $avatar instanceof UploadedFile
            && is_string($previousAvatarDisk)
            && is_string($previousAvatarPath)
            && ! ($previousAvatarDisk === $updatedUser->avatar_disk && $previousAvatarPath === $updatedUser->avatar_path)
        ) {
            try {
                Storage::disk($previousAvatarDisk)->delete($previousAvatarPath);
            } catch (Throwable $throwable) {
                $this->securityLogger->warning('admin_auth.avatar_cleanup_failed', [
                    'error' => $throwable->getMessage(),
                    'user' => $user,
                ]);
            }
        }

        return $updatedUser;
    }
}
