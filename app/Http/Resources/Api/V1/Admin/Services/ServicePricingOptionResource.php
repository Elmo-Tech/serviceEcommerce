<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Services;

use App\Models\ServicePricingOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServicePricingOption */
class ServicePricingOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nameAr' => $this->name_ar,
            'nameEn' => $this->name_en,
            'inputType' => $this->input_type?->value,
            'isRequired' => (bool) $this->is_required,
            'sortOrder' => (int) $this->sort_order,
            'values' => ServicePricingOptionValueResource::collection(
                $this->whenLoaded('values', $this->values->whereNull('deleted_at')->sortBy(['sort_order', 'id'])->values()),
            )->resolve(),
        ];
    }
}
