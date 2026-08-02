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
        Schema::create('order_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->char('idempotency_key', 36)->unique();
            $table->char('request_fingerprint', 64);
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->cascadeOnDelete();
            $table->timestamp('reserved_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('order_id', 'order_idempotency_keys_order_id_unique');
            $table->index(['idempotency_key', 'request_fingerprint'], 'order_idempotency_keys_key_fingerprint_idx');
        });

        DB::statement(<<<'SQL'
ALTER TABLE order_idempotency_keys
ADD CONSTRAINT order_idempotency_keys_completion_pair_check
CHECK (
    (`order_id` IS NULL AND `completed_at` IS NULL)
    OR
    (`order_id` IS NOT NULL AND `completed_at` IS NOT NULL)
)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_idempotency_keys');
    }
};
