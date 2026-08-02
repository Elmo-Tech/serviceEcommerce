<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class SettingsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = (string) config('auth.defaults.guard', 'web');

        foreach (['settings.view', 'settings.update'] as $permission) {
            Permission::findOrCreate($permission, $guard);
        }
    }
}
