<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoginSuccessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'accessToken' => $this->resource['accessToken'],
            'refreshToken' => $this->resource['refreshToken'],
            'tokenType' => $this->resource['tokenType'],
            'tokenExpiresIn' => $this->resource['tokenExpiresIn'],
            'refreshTokenExpiresIn' => $this->resource['refreshTokenExpiresIn'],
            'profile' => new AdminProfileResource($this->resource['user']),
        ];
    }
}
