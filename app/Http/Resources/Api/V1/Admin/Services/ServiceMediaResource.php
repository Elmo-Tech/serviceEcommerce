<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Services;

use App\Models\ServiceMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin ServiceMedia */
class ServiceMediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'url' => Storage::disk($this->disk)->url($this->path),
            'originalName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'extension' => $this->extension,
            'sizeBytes' => (int) $this->size_bytes,
            'altAr' => $this->alt_text_ar,
            'altEn' => $this->alt_text_en,
            'isMain' => (bool) $this->is_main,
        ];
    }
}
