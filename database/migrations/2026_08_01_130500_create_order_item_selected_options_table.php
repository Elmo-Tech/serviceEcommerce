<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_selected_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('pricing_option_id')->nullable();
            $table->string('option_name_ar', 255);
            $table->string('option_name_en', 255);
            $table->unsignedTinyInteger('input_type');
            $table->boolean('is_required');
            $table->timestamps();

            $table->unique(
                ['order_item_id', 'pricing_option_id'],
                'order_item_selected_options_item_option_unique',
            );
            $table->index(['order_item_id', 'id'], 'order_item_selected_options_item_id_idx');
            $table->foreign('pricing_option_id', 'oiso_pricing_option_fk')
                ->references('id')
                ->on('service_pricing_options')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_selected_options');
    }
};
