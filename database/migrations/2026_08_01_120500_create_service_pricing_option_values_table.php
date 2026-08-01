<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_pricing_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_pricing_option_id')
                ->constrained('service_pricing_options')
                ->restrictOnDelete();
            $table->string('label_ar', 150);
            $table->string('label_en', 150);
            $table->decimal('price_adjustment', 12, 2)->default('0.00');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['service_pricing_option_id', 'deleted_at', 'is_active', 'sort_order', 'id'],
                'idx_service_pricing_values_parent_active_order',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_pricing_option_values');
    }
};
