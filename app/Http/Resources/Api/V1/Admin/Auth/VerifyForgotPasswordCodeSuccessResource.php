<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerifyForgotPasswordCodeSuccessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'resetToken' => $this->resource['resetToken'],
            'resetTokenExpiresIn' => $this->resource['resetTokenExpiresIn'],
        ];
    }
}
