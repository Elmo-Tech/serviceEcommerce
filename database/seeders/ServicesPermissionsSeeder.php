<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ServicesPermissionsSeeder extends Seeder
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
            'services.view',
            'services.create',
            'services.update',
            'services.delete',
            'services.restore',
            'service-specifications.view',
            'service-specifications.create',
            'service-specifications.update',
            'service-specifications.delete',
            'service-order-fields.view',
            'service-order-fields.create',
            'service-order-fields.update',
            'service-order-fields.delete',
            'service-pricing-options.view',
            'service-pricing-options.create',
            'service-pricing-options.update',
            'service-pricing-options.delete',
            'service-media.view',
            'service-media.create',
            'service-media.update',
            'service-media.delete',
            'service-media.set-main',
        ];
    }
}
