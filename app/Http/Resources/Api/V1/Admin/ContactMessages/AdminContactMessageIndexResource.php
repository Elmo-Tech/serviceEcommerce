<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\ContactMessages;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContactMessage
 */
class AdminContactMessageIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'status' => $this->status->key(),
            'createdAt' => $this->created_at?->toJSON(),
        ];
    }
}
