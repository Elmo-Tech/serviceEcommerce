<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use App\Models\SettingPhone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettingPhone>
 */
class SettingPhoneFactory extends Factory
{
    protected $model = SettingPhone::class;

    public function definition(): array
    {
        return [
            'setting_id' => Setting::factory(),
            'number' => '01012345678',
            'has_whats' => 1,
            'position' => 0,
        ];
    }
}
