<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Services;

use App\Models\ServiceSpecification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceSpecification */
class ServiceSpecificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'labelAr' => $this->label_ar,
            'labelEn' => $this->label_en,
            'valueAr' => $this->value_ar,
            'valueEn' => $this->value_en,
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}
