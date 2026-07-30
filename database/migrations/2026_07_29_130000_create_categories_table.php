<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('slug_ar', 180)->unique();
            $table->string('slug_en', 180)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('is_active');
            $table->index('deleted_at');
            $table->index(['parent_id', 'is_active', 'sort_order', 'id'], 'categories_parent_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
