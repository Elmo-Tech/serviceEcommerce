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
            $table->string('province', 150)->default('')->after('phone_normalized');
            $table->string('address', 500)->default('')->after('province');
        });

        DB::statement("
            UPDATE customer_addresses
            SET
                province = COALESCE(city, ''),
                address = TRIM(
                    CONCAT_WS(' | ', NULLIF(area, ''), NULLIF(street, ''))
                )
        ");

        DB::statement("
            UPDATE customer_addresses
            SET address = province
            WHERE address = ''
        ");

        DB::statement("
            UPDATE customer_addresses
            SET address_hash = SHA2(
                LOWER(CONCAT_WS('|', province, address)),
                256
            )
        ");

        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->dropIndex(['country_code', 'city']);
            $table->dropColumn(['label', 'country_code', 'city', 'area', 'street']);
            $table->index(['province'], 'customer_addresses_province_index');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->string('label', 100)->nullable()->after('customer_id');
            $table->string('country_code', 2)->default('EG')->after('phone_normalized');
            $table->string('city', 150)->default('')->after('country_code');
            $table->string('area', 150)->nullable()->after('city');
            $table->string('street', 255)->default('')->after('area');
        });

        DB::statement("
            UPDATE customer_addresses
            SET
                country_code = 'EG',
                city = COALESCE(province, ''),
                area = NULL,
                street = COALESCE(address, '')
        ");

        DB::statement("
            UPDATE customer_addresses
            SET address_hash = SHA2(
                LOWER(CONCAT_WS('|', country_code, city, COALESCE(area, ''), street)),
                256
            )
        ");

        Schema::table('customer_addresses', function (Blueprint $table): void {
            $table->dropIndex('customer_addresses_province_index');
            $table->dropColumn(['province', 'address']);
            $table->index(['country_code', 'city']);
        });
    }
};
