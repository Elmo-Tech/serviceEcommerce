<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class AdminProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->roles->pluck('name')->sort()->values()->first();
        $permissions = $this->getAllPermissions()->pluck('name')->sort()->values()->all();

        return [
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatarUrl(),
            'role' => $role,
            'permissions' => $permissions,
        ];
    }

    private function avatarUrl(): ?string
    {
        if (! is_string($this->avatar_disk) || ! is_string($this->avatar_path)) {
            return null;
        }

        return Storage::disk($this->avatar_disk)->url($this->avatar_path);
    }
}
