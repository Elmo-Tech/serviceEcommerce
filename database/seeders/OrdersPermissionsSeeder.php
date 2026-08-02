<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class OrdersPermissionsSeeder extends Seeder
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
            'orders.view',
            'orders.create',
            'orders.update',
            'orders.delete',
            'orders.change-status',
            'orders.manage-payment',
            'order-items.view',
            'order-items.create',
            'order-items.update',
            'order-items.delete',
            'order-item-attachments.create',
            'order-item-attachments.delete',
        ];
    }
}
