<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unique('name_ar', 'uq_services_name_ar');
            $table->unique('name_en', 'uq_services_name_en');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique('uq_services_name_ar');
            $table->dropUnique('uq_services_name_en');
        });
    }
};
