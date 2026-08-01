<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Services;

use App\Models\ServicePricingOptionValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServicePricingOptionValue */
class ServicePricingOptionValueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'labelAr' => $this->label_ar,
            'labelEn' => $this->label_en,
            'priceAdjustment' => (float) $this->price_adjustment,
            'isActive' => (bool) $this->is_active,
            'sortOrder' => (int) $this->sort_order,
        ];
    }
}
