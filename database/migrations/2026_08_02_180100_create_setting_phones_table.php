<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setting_phones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('setting_id')->constrained('settings')->cascadeOnDelete();
            $table->string('number', 20);
            $table->unsignedTinyInteger('has_whats')->default(0);
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['setting_id', 'number']);
            $table->index(['setting_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_phones');
    }
};
