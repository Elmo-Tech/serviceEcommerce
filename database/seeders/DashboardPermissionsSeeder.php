<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class DashboardPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Permission::findOrCreate(
            'dashboard.view',
            (string) config('auth.defaults.guard', 'web'),
        );
    }
}
