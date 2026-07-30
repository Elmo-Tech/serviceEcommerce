<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CategoriesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = (string) config('auth.defaults.guard', 'web');

        foreach ($this->permissions() as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }
    }

    /**
     * @return list<string>
     */
    private function permissions(): array
    {
        return [
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
    }
}
