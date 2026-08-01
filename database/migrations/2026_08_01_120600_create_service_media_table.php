<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('type');
            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('stored_name', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 150);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->string('alt_text_ar', 255)->nullable();
            $table->string('alt_text_en', 255)->nullable();
            $table->boolean('is_main')->default(false);
            $table->timestamps();

            $table->index(
                ['service_id', 'type', 'is_main', 'id'],
                'idx_service_media_parent_type_main',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_media');
    }
};
