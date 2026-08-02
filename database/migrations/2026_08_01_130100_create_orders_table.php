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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 17)->unique();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 11);
            $table->string('customer_email', 255)->nullable();
            $table->foreignId('customer_address_id')
                ->nullable()
                ->constrained('customer_addresses')
                ->nullOnDelete();
            $table->string('address_province', 150)->nullable();
            $table->string('address_city', 150)->nullable();
            $table->string('address_text', 1000)->nullable();
            $table->unsignedTinyInteger('status');
            $table->unsignedTinyInteger('order_place');
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->decimal('subtotal', 12, 2)->default('0.00');
            $table->unsignedTinyInteger('discount_type')->nullable();
            $table->decimal('discount_value', 12, 2)->default('0.00');
            $table->decimal('discount_amount', 12, 2)->default('0.00');
            $table->text('discount_reason')->nullable();
            $table->decimal('total', 12, 2)->default('0.00');
            $table->unsignedTinyInteger('payment_status');
            $table->decimal('paid_amount', 12, 2)->default('0.00');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at', 'id'], 'orders_status_created_id_idx');
            $table->index(['payment_status', 'created_at', 'id'], 'orders_payment_status_created_id_idx');
            $table->index(['order_place', 'created_at', 'id'], 'orders_place_created_id_idx');
            $table->index(['customer_id', 'created_at', 'id'], 'orders_customer_created_id_idx');
            $table->index(['total', 'id'], 'orders_total_id_idx');
            $table->index('customer_phone', 'orders_customer_phone_idx');
            $table->index('customer_email', 'orders_customer_email_idx');
            $table->index('customer_name', 'orders_customer_name_idx');
        });

        DB::statement(<<<'SQL'
ALTER TABLE orders
ADD CONSTRAINT orders_status_check
CHECK (`status` IN (0, 1, 2, 3, 4))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE orders
ADD CONSTRAINT orders_payment_status_check
CHECK (`payment_status` IN (0, 1, 2))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE orders
ADD CONSTRAINT orders_discount_type_check
CHECK (`discount_type` IS NULL OR `discount_type` IN (0, 1))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE orders
ADD CONSTRAINT orders_place_check
CHECK (`order_place` IN (0, 1))
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE orders
ADD CONSTRAINT orders_address_snapshot_all_or_none_check
CHECK (
    (`address_province` IS NULL AND `address_city` IS NULL AND `address_text` IS NULL)
    OR
    (`address_province` IS NOT NULL AND `address_city` IS NOT NULL AND `address_text` IS NOT NULL)
)
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
