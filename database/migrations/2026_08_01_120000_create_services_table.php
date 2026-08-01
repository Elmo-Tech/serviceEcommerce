<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();
            $table->foreignId('subcategory_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('short_description_ar', 500);
            $table->string('short_description_en', 500);
            $table->text('description_ar');
            $table->text('description_en');
            $table->string('slug_ar', 180)->unique('uq_services_slug_ar');
            $table->string('slug_en', 180)->unique('uq_services_slug_en');
            $table->string('production_time_ar', 255)->nullable();
            $table->string('production_time_en', 255)->nullable();
            $table->unsignedTinyInteger('price_type');
            $table->decimal('base_price', 12, 2);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_available')->default(true);
            $table->string('seo_title_ar', 70)->nullable();
            $table->string('seo_title_en', 70)->nullable();
            $table->string('seo_description_ar', 180)->nullable();
            $table->string('seo_description_en', 180)->nullable();
            $table->json('seo_tags_ar')->nullable();
            $table->json('seo_tags_en')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['is_active', 'deleted_at', 'created_at', 'id'],
                'idx_services_public_created',
            );
            $table->index(
                ['category_id', 'is_active', 'deleted_at', 'created_at', 'id'],
                'idx_services_category_public',
            );
            $table->index(
                ['subcategory_id', 'is_active', 'deleted_at', 'created_at', 'id'],
                'idx_services_subcategory_public',
            );
            $table->index(
                ['is_active', 'deleted_at', 'base_price', 'id'],
                'idx_services_public_price',
            );
            $table->index(
                ['price_type', 'deleted_at', 'created_at', 'id'],
                'idx_services_admin_type_created',
            );
            $table->index(
                ['is_available', 'deleted_at', 'created_at', 'id'],
                'idx_services_admin_available_created',
            );
            $table->index(
                ['is_active', 'deleted_at', 'created_at', 'id'],
                'idx_services_admin_active_created',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
