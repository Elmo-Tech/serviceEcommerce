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

        $role = Role::findOrCreate('super-admin', $guard);
        $permissionNames = [
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'customers.restore',
            'customer-addresses.view',
            'customer-addresses.create',
            'customer-addresses.update',
            'customer-addresses.delete',
            'customer-addresses.restore',
            'customer-addresses.set-default',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.restore',
            'categories.reorder',
            'subcategories.view',
            'subcategories.create',
            'subcategories.update',
            'subcategories.delete',
            'subcategories.restore',
            'subcategories.reorder',
        ];

        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role->syncPermissions(
            Permission::query()
                ->where('guard_name', $guard)
                ->get(),
        );
    }
}
