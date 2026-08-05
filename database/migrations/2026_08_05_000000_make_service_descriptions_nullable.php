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
        Schema::table('services', function (Blueprint $table): void {
            $table->text('description_ar')->nullable()->change();
            $table->text('description_en')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('services')->whereNull('description_ar')->update(['description_ar' => '']);
        DB::table('services')->whereNull('description_en')->update(['description_en' => '']);

        Schema::table('services', function (Blueprint $table): void {
            $table->text('description_ar')->nullable(false)->change();
            $table->text('description_en')->nullable(false)->change();
        });
    }
};
