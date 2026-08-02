<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Settings\SocialPlatform;
use App\Models\Setting;
use App\Models\SettingSocialLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettingSocialLink>
 */
class SettingSocialLinkFactory extends Factory
{
    protected $model = SettingSocialLink::class;

    public function definition(): array
    {
        return [
            'setting_id' => Setting::factory(),
            'platform' => SocialPlatform::FACEBOOK,
            'url' => 'https://facebook.com/example',
            'position' => 0,
        ];
    }
}
