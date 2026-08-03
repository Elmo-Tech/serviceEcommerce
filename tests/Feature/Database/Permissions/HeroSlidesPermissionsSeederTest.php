<?php

declare(strict_types=1);

use Database\Seeders\HeroSlidesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('idempotently creates only the four approved Hero permissions', function () {
    $this->seed(HeroSlidesPermissionsSeeder::class);
    $this->seed(HeroSlidesPermissionsSeeder::class);

    expect(Permission::query()->where('name', 'like', 'hero-slides.%')->orderBy('name')->pluck('name')->all())
        ->toBe([
            'hero-slides.create',
            'hero-slides.delete',
            'hero-slides.update',
            'hero-slides.view',
        ]);
});
