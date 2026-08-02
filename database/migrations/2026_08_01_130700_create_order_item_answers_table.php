<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('service_order_field_id')->nullable();
            $table->string('question_ar', 255);
            $table->string('question_en', 255);
            $table->unsignedTinyInteger('field_type');
            $table->boolean('is_required');
            $table->text('answer');
            $table->timestamps();

            $table->unique(
                ['order_item_id', 'service_order_field_id'],
                'order_item_answers_item_field_unique',
            );
            $table->index(['order_item_id', 'id'], 'order_item_answers_item_id_idx');
            $table->foreign('service_order_field_id', 'order_item_answers_field_fk')
                ->references('id')
                ->on('service_order_fields')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_answers');
    }
};
