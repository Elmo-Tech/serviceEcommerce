<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('site_name_ar');
            $table->string('site_name_en');
            $table->string('site_description_ar', 500)->nullable();
            $table->string('site_description_en', 500)->nullable();
            $table->string('slogan_ar')->nullable();
            $table->string('slogan_en')->nullable();
            $table->text('address_ar')->nullable();
            $table->text('address_en')->nullable();
            $table->string('public_email');
            $table->string('logo_path')->nullable();
            $table->string('footer_logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->text('google_maps_url')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('default_seo_title_ar')->nullable();
            $table->string('default_seo_title_en')->nullable();
            $table->string('default_seo_description_ar')->nullable();
            $table->string('default_seo_description_en')->nullable();
            $table->json('default_seo_keywords_ar')->nullable();
            $table->json('default_seo_keywords_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
