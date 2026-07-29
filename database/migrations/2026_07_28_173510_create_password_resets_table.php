<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->id();
            $table->morphs('resettable');
            $table->string('email_normalized');
            $table->string('code_hash');
            $table->timestamp('code_expires_at');
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->char('reset_token_hash', 64)->nullable()->unique();
            $table->timestamp('reset_token_expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index('email_normalized');
            $table->index('code_expires_at');
            $table->index('reset_token_expires_at');
            $table->index('consumed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
