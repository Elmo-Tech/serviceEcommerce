<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn([
                'site_name_ar',
                'site_name_en',
                'site_description_ar',
                'site_description_en',
                'slogan_ar',
                'slogan_en',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->string('site_name_ar')->nullable()->after('id');
            $table->string('site_name_en')->nullable()->after('site_name_ar');
            $table->string('site_description_ar', 500)->nullable()->after('site_name_en');
            $table->string('site_description_en', 500)->nullable()->after('site_description_ar');
            $table->string('slogan_ar')->nullable()->after('site_description_en');
            $table->string('slogan_en')->nullable()->after('slogan_ar');
        });
    }
};
