<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\Settings\SocialPlatform;
use App\Models\Setting;

class SettingsResolver
{
    /**
     * @return array<string, mixed>
     */
    public function placeholderAttributes(): array
    {
        return [
            'site_name_ar' => 'اسم الموقع',
            'site_name_en' => 'Website Name',
            'site_description_ar' => null,
            'site_description_en' => null,
            'slogan_ar' => null,
            'slogan_en' => null,
            'address_ar' => null,
            'address_en' => null,
            'public_email' => 'info@example.com',
            'logo_path' => null,
            'footer_logo_path' => null,
            'favicon_path' => null,
            'google_maps_url' => null,
            'latitude' => null,
            'longitude' => null,
            'default_seo_title_ar' => null,
            'default_seo_title_en' => null,
            'default_seo_description_ar' => null,
            'default_seo_description_en' => null,
            'default_seo_keywords_ar' => [],
            'default_seo_keywords_en' => [],
        ];
    }

    public function resolveForAdmin(bool $lockForUpdate = false): Setting
    {
        Setting::query()->insertOrIgnore([
            array_merge(
                ['id' => 1],
                $this->databasePlaceholderAttributes(),
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ),
        ]);

        $query = Setting::query()->whereKey(1);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        /** @var Setting */
        return $query->firstOrFail();
    }

    public function resolveForPublic(): ?Setting
    {
        return Setting::query()->with(['phones', 'socialLinks'])->find(1);
    }

    /**
     * @return array<string, mixed>
     */
    public function safePublicDefaults(string $locale = 'ar'): array
    {
        $isArabic = $locale !== 'en';

        return [
            'siteName' => $isArabic ? 'اسم الموقع' : 'Website Name',
            'siteDescription' => null,
            'slogan' => null,
            'logo' => null,
            'footerLogo' => null,
            'favicon' => null,
            'email' => 'info@example.com',
            'phones' => [],
            'address' => null,
            'socialLinks' => [],
        ];
    }

    /**
     * @return list<string>
     */
    public function availableSocialPlatforms(): array
    {
        return SocialPlatform::keys();
    }

    /**
     * @return array<string, mixed>
     */
    private function databasePlaceholderAttributes(): array
    {
        $attributes = $this->placeholderAttributes();

        $attributes['default_seo_keywords_ar'] = json_encode($attributes['default_seo_keywords_ar'], JSON_THROW_ON_ERROR);
        $attributes['default_seo_keywords_en'] = json_encode($attributes['default_seo_keywords_en'], JSON_THROW_ON_ERROR);

        return $attributes;
    }
}
