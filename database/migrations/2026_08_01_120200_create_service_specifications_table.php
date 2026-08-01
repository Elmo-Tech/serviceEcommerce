<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_specifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();
            $table->string('label_ar', 150);
            $table->string('label_en', 150);
            $table->string('value_ar', 1000);
            $table->string('value_en', 1000);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['service_id', 'deleted_at', 'sort_order', 'id'],
                'idx_service_specifications_parent_order',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_specifications');
    }
};
