<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the categories table with the approved columns and indexes', function () {
    expect(Schema::hasTable('categories'))->toBeTrue();

    foreach ([
        'id',
        'parent_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'slug_ar',
        'slug_en',
        'sort_order',
        'is_active',
        'created_at',
        'updated_at',
        'deleted_at',
    ] as $column) {
        expect(Schema::hasColumn('categories', $column))->toBeTrue();
    }

    $indexes = collect(DB::select('SHOW INDEX FROM categories'))
        ->pluck('Key_name')
        ->unique()
        ->values()
        ->all();

    expect($indexes)->toContain('PRIMARY')
        ->and($indexes)->toContain('categories_slug_ar_unique')
        ->and($indexes)->toContain('categories_slug_en_unique')
        ->and($indexes)->toContain('categories_parent_active_order_idx');
});

it('supports root and subcategory scopes and relationships', function () {
    $root = Category::factory()->root()->create();
    $child = Category::factory()->subcategory($root)->create();

    expect(Category::query()->roots()->pluck('id')->all())->toContain($root->getKey())
        ->and(Category::query()->subcategories()->pluck('id')->all())->toContain($child->getKey())
        ->and($child->fresh()->parent?->is($root))->toBeTrue()
        ->and($root->fresh()->children->first()?->is($child))->toBeTrue()
        ->and($root->isRoot())->toBeTrue()
        ->and($child->isSubcategory())->toBeTrue();
});
