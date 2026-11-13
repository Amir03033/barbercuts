<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meer zelf in te richten op de pagina van de zaak: een banner (tekst met een optionele afbeelding) en links
 * naar Instagram en de eigen website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->boolean('banner_active')->default(false)->after('cover_path');
            $table->string('banner_text', 160)->nullable()->after('banner_active');
            $table->string('banner_path')->nullable()->after('banner_text');
            $table->string('instagram', 60)->nullable()->after('banner_path');
            $table->string('website')->nullable()->after('instagram');
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', fn (Blueprint $table) => $table->dropColumn(['banner_active', 'banner_text', 'banner_path', 'instagram', 'website']));
    }
};
