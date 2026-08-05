<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->firstOrCreate(
            ['id' => 1],
            [
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
            ],
        );
    }
}
