<?php

declare(strict_types=1);

use Database\Seeders\HeroSlidesPermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('idempotently creates only the four approved Hero permissions', function () {
    Permission::findOrCreate('unrelated.permission', 'web');
    $this->seed(HeroSlidesPermissionsSeeder::class);
    $this->seed(HeroSlidesPermissionsSeeder::class);

    expect(Permission::query()->where('name', 'like', 'hero-slides.%')->orderBy('name')->pluck('name')->all())
        ->toBe([
            'hero-slides.create',
            'hero-slides.delete',
            'hero-slides.update',
            'hero-slides.view',
        ])
        ->and(Permission::findByName('unrelated.permission', 'web'))->not->toBeNull()
        ->and(Permission::query()->whereIn('name', ['hero-slides.*', 'hero-slides.reorder'])->exists())->toBeFalse();
});

it('synchronizes the configured guard permissions to super admin without dropping unrelated permissions', function () {
    seedAdminAuthEnvironment();
    Permission::findOrCreate('unrelated.permission', 'web');
    $this->seed(SuperAdminSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    $role = Role::findByName('super-admin', 'web');
    expect($role->hasAllPermissions([
        'hero-slides.view', 'hero-slides.create', 'hero-slides.update', 'hero-slides.delete', 'unrelated.permission',
    ]))->toBeTrue();
});
