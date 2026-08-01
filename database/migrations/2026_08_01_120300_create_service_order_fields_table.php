<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();
            $table->string('label_ar', 150);
            $table->string('label_en', 150);
            $table->unsignedTinyInteger('field_type')->default(0);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['service_id', 'deleted_at', 'sort_order', 'id'],
                'idx_service_order_fields_parent_order',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_fields');
    }
};
