<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_pricing_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->unsignedTinyInteger('option_type')->default(0);
            $table->unsignedTinyInteger('input_type');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['service_id', 'deleted_at', 'sort_order', 'id'],
                'idx_service_pricing_options_parent_order',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_pricing_options');
    }
};
