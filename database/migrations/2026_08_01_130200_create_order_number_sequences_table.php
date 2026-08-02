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
        Schema::create('order_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->date('business_date')->unique();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
ALTER TABLE order_number_sequences
ADD CONSTRAINT order_number_sequences_last_sequence_check
CHECK (`last_sequence` BETWEEN 0 AND 9999)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
