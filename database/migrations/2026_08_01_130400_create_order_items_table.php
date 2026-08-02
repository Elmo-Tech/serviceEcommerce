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
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('service_id')
                ->nullable()
                ->constrained('services')
                ->nullOnDelete();
            $table->string('service_name_ar', 255);
            $table->string('service_name_en', 255);
            $table->string('service_slug_ar', 255);
            $table->string('service_slug_en', 255);
            $table->unsignedTinyInteger('price_type');
            $table->decimal('base_price', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('item_total', 12, 2);
            $table->text('item_note')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'id'], 'order_items_order_id_id_idx');
            $table->index(['service_id', 'order_id'], 'order_items_service_order_idx');
        });

        DB::statement(<<<'SQL'
ALTER TABLE order_items
ADD CONSTRAINT order_items_quantity_check
CHECK (`quantity` >= 1)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
