<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('question_ar', 500);
            $table->string('question_en', 500);
            $table->text('answer_ar');
            $table->text('answer_en');
            $table->boolean('is_active');
            $table->unsignedInteger('position')->unique();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
