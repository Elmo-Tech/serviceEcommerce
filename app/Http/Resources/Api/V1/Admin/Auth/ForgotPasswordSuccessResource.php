<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForgotPasswordSuccessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, never>
     */
    public function toArray(Request $request): array
    {
        return [];
    }
}
