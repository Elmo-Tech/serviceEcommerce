<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\HeroSlides;

use App\Models\HeroSlide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HeroSlide
 */
class AdminHeroSlideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'titleAr' => $this->title_ar,
            'titleEn' => $this->title_en,
            'descriptionAr' => $this->description_ar,
            'descriptionEn' => $this->description_en,
            'image' => $this->imageUrl(),
            'isActive' => (int) $this->is_active,
            'position' => (int) $this->position,
        ];
    }
}
