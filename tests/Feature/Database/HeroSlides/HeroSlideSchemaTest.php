<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has the single required hero slides table without soft deletes', function () {
    expect(Schema::hasTable('hero_slides'))->toBeTrue()
        ->and(Schema::hasColumns('hero_slides', [
            'id', 'title_ar', 'title_en', 'description_ar', 'description_en',
            'image_path', 'is_active', 'position', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('hero_slides', 'deleted_at'))->toBeFalse();
});
