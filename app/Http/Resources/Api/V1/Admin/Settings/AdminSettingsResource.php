<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin\Settings;

use App\Models\Setting;
use App\Services\Settings\SettingsResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Setting
 */
class AdminSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'logo' => $this->logoUrl(),
            'footerLogo' => $this->footerLogoUrl(),
            'favicon' => $this->faviconUrl(),
            'publicEmail' => $this->public_email,
            'phones' => $this->phones->map(fn ($phone) => [
                'number' => $phone->number,
                'hasWhats' => (int) $phone->has_whats,
            ])->values()->all(),
            'addressAr' => $this->address_ar,
            'addressEn' => $this->address_en,
            'googleMapsUrl' => $this->google_maps_url,
            'socialLinks' => $this->socialLinks->map(fn ($link) => [
                'platform' => $link->platform?->key(),
                'url' => $link->url,
            ])->values()->all(),
            'availableSocialPlatforms' => app(SettingsResolver::class)->availableSocialPlatforms(),
        ];
    }
}
