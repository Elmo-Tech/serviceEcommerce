<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Public\HeroSlides;

use App\Models\HeroSlide;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HeroSlide
 */
class PublicHeroSlideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isEnglish = app()->getLocale() === 'en';

        return [
            'title' => $isEnglish ? $this->title_en : $this->title_ar,
            'description' => $isEnglish ? $this->description_en : $this->description_ar,
            'image' => $this->imageUrl(),
        ];
    }
}
