<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Public\Settings;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Setting
 */
class PublicSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        $isArabic = app()->getLocale() !== 'en';

        return [
            'siteName' => $isArabic ? $this->site_name_ar : $this->site_name_en,
            'siteDescription' => $isArabic ? $this->site_description_ar : $this->site_description_en,
            'slogan' => $isArabic ? $this->slogan_ar : $this->slogan_en,
            'logo' => $this->logoUrl(),
            'footerLogo' => $this->footerLogoUrl(),
            'favicon' => $this->faviconUrl(),
            'email' => $this->public_email,
            'phones' => $this->phones->map(fn ($phone) => [
                'number' => $phone->number,
                'hasWhats' => (int) $phone->has_whats,
            ])->values()->all(),
            'address' => $isArabic ? $this->address_ar : $this->address_en,
            'googleMapsUrl' => $this->google_maps_url,
            'socialLinks' => $this->socialLinks->map(fn ($link) => [
                'platform' => $link->platform?->key(),
                'url' => $link->url,
            ])->values()->all(),
        ];
    }
}
