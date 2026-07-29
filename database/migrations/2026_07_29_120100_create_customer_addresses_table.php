<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100)->nullable();
            $table->string('phone', 30);
            $table->string('phone_normalized', 30);
            $table->string('country_code', 2);
            $table->string('city', 150);
            $table->string('area', 150)->nullable();
            $table->string('street', 255);
            $table->text('notes')->nullable();
            $table->string('address_hash', 64);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'deleted_at']);
            $table->index(['customer_id', 'is_default', 'deleted_at']);
            $table->index(['customer_id', 'address_hash', 'deleted_at']);
            $table->index(['country_code', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
