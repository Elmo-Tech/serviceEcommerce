<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->string('stored_name', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index(['order_item_id', 'id'], 'order_item_attachments_item_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_attachments');
    }
};
