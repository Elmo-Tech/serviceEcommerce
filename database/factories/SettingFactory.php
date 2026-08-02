<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'id' => 1,
            'site_name_ar' => 'اسم الموقع',
            'site_name_en' => 'Website Name',
            'site_description_ar' => 'وصف الموقع باللغة العربية',
            'site_description_en' => 'Website description in English',
            'slogan_ar' => 'الشعار النصي بالعربية',
            'slogan_en' => 'English slogan',
            'address_ar' => 'القاهرة، مصر',
            'address_en' => 'Cairo, Egypt',
            'public_email' => 'info@example.com',
            'logo_path' => null,
            'footer_logo_path' => null,
            'favicon_path' => null,
            'google_maps_url' => 'https://maps.google.com/example',
            'latitude' => '30.0444000',
            'longitude' => '31.2357000',
            'default_seo_title_ar' => 'العنوان الافتراضي للموقع',
            'default_seo_title_en' => 'Default website SEO title',
            'default_seo_description_ar' => 'وصف SEO الافتراضي باللغة العربية',
            'default_seo_description_en' => 'Default SEO description in English',
            'default_seo_keywords_ar' => ['خدمات', 'تصميم'],
            'default_seo_keywords_en' => ['services', 'design'],
        ];
    }
}
