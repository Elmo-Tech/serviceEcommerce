<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 254)->nullable();
            $table->string('phone', 30);
            $table->string('subject', 200);
            $table->text('message');
            $table->unsignedTinyInteger('status');
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index('email');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
