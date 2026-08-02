<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_email_unique');
            $table->dropUnique('customers_phone_normalized_unique');

            $table->index('email', 'customers_email_idx');
            $table->index('phone_normalized', 'customers_phone_normalized_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_email_idx');
            $table->dropIndex('customers_phone_normalized_idx');

            $table->unique('email');
            $table->unique('phone_normalized');
        });
    }
};
