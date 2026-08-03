<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class FaqPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = (string) config('auth.defaults.guard', 'web');

        foreach ($this->permissions() as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function permissions(): array
    {
        return [
            'faqs.view',
            'faqs.create',
            'faqs.update',
            'faqs.delete',
        ];
    }
}
