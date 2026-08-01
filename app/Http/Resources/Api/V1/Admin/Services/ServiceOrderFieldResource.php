<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Services;

use App\Models\ServiceOrderField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceOrderField */
class ServiceOrderFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'labelAr' => $this->label_ar,
            'labelEn' => $this->label_en,
            'isRequired' => (bool) $this->is_required,
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}
