<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = (string) config('auth.defaults.guard', 'web');

        $this->call([
            CustomerPermissionsSeeder::class,
            CategoriesPermissionsSeeder::class,
            ServicesPermissionsSeeder::class,
        ]);

        $role = Role::findOrCreate('super-admin', $guard);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->syncPermissions(
            Permission::query()
                ->where('guard_name', $guard)
                ->get(),
        );
    }
}
