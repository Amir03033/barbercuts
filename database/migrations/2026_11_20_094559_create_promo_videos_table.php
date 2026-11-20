<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promovideo's (motion graphics) die de beheerder uploadt in /admin en die op de demopagina, de landingspagina en in
 * het pitcherdashboard verschijnen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_videos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('caption', 255)->nullable();
            $table->string('file_path');
            $table->string('poster_path')->nullable();
            $table->string('orientation', 12)->default('landscape');
            $table->boolean('show_on_demo')->default(false);
            $table->boolean('show_on_home')->default(false);
            $table->boolean('for_pitchers')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_videos');
    }
};
