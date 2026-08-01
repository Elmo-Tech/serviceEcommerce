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
        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->string('city', 150)->default('')->after('province');
            $table->index('city');
        });

        DB::statement("
            UPDATE customer_addresses
            SET city = COALESCE(NULLIF(province, ''), '')
        ");

        DB::statement("
            UPDATE customer_addresses
            SET address_hash = SHA2(
                LOWER(CONCAT_WS('|', province, city, address)),
                256
            )
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE customer_addresses
            SET address_hash = SHA2(
                LOWER(CONCAT_WS('|', province, address)),
                256
            )
        ");

        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->dropIndex(['city']);
            $table->dropColumn('city');
        });
    }
};
