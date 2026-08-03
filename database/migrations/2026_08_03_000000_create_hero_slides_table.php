<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table): void {
            $table->id();
            $table->string('title_ar', 150);
            $table->string('title_en', 150);
            $table->text('description_ar');
            $table->text('description_en');
            $table->string('image_path');
            $table->boolean('is_active');
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique('position', 'hero_slides_position_unique');
            $table->index(['is_active', 'position'], 'hero_slides_active_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
