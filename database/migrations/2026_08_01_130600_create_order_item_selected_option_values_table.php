<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_selected_option_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_selected_option_id');
            $table->foreignId('pricing_option_value_id')->nullable();
            $table->string('value_label_ar', 255);
            $table->string('value_label_en', 255);
            $table->decimal('price_adjustment', 12, 2)->default('0.00');
            $table->timestamps();

            $table->unique(
                ['order_item_selected_option_id', 'pricing_option_value_id'],
                'order_item_selected_option_values_option_value_unique',
            );
            $table->index(
                ['order_item_selected_option_id', 'id'],
                'order_item_selected_option_values_option_id_idx',
            );
            $table->foreign('order_item_selected_option_id', 'oisov_selected_option_fk')
                ->references('id')
                ->on('order_item_selected_options')
                ->cascadeOnDelete();
            $table->foreign('pricing_option_value_id', 'oisov_pricing_value_fk')
                ->references('id')
                ->on('service_pricing_option_values')
                ->nullOnDelete();
        });

        DB::statement(<<<'SQL'
ALTER TABLE order_item_selected_option_values
ADD CONSTRAINT order_item_selected_option_values_price_adjustment_check
CHECK (`price_adjustment` >= 0)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_selected_option_values');
    }
};
