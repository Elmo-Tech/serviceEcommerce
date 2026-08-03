<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has the single required hero slides table without soft deletes', function () {
    expect(Schema::hasTable('hero_slides'))->toBeTrue()
        ->and(Schema::hasColumns('hero_slides', [
            'id', 'title_ar', 'title_en', 'description_ar', 'description_en',
            'image_path', 'is_active', 'position', 'created_at', 'updated_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumn('hero_slides', 'deleted_at'))->toBeFalse();

    $columns = collect(DB::select('SHOW COLUMNS FROM hero_slides'))->keyBy('Field');
    expect(strtolower((string) $columns['id']->Type))->toContain('bigint')->toContain('unsigned')
        ->and(strtolower((string) $columns['position']->Type))->toContain('tinyint')->toContain('unsigned')
        ->and(strtolower((string) $columns['is_active']->Type))->toContain('tinyint')
        ->and($columns['title_ar']->Null)->toBe('NO')
        ->and($columns['image_path']->Null)->toBe('NO');

    $indexes = collect(DB::select('SHOW INDEX FROM hero_slides'))->groupBy('Key_name');
    expect($indexes)->toHaveKeys([
        'PRIMARY',
        'hero_slides_position_unique',
        'hero_slides_active_position_index',
    ])
        ->and((int) $indexes['hero_slides_position_unique']->first()->Non_unique)->toBe(0)
        ->and($indexes['hero_slides_active_position_index']->sortBy('Seq_in_index')->pluck('Column_name')->all())
        ->toBe(['is_active', 'position']);

    $heroTables = collect(DB::select("SHOW TABLES LIKE 'hero_slide%'"))
        ->flatMap(fn (object $row): array => array_values((array) $row))
        ->all();

    expect($heroTables)->toBe(['hero_slides']);
});
