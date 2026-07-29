<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class CustomerPermissionsSeeder extends Seeder
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
        ];
    }
}
