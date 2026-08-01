<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_slug_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();
            $table->string('slug', 180)->unique('uq_service_slug_reservations_slug');
            $table->timestamps();

            $table->index('service_id', 'idx_service_slug_reservations_service');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_slug_reservations');
    }
};
