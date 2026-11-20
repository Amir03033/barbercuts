<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wat een kapper met een persoonlijke demo doet, zodat de pitcher de voortgang ziet: hoe vaak de link geopend is,
 * hoe lang en waar er rondgekeken is, hoeveel afspraken er gemaakt zijn en of het aanmelden begon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_invites', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0)->after('accent_color');
            $table->timestamp('last_viewed_at')->nullable()->after('views');
        });

        Schema::table('demo_visits', function (Blueprint $table) {
            $table->unsignedInteger('page_views')->default(0)->after('visitor_id');
            $table->json('explored')->nullable()->after('page_views');
            $table->unsignedInteger('appointments_made')->default(0)->after('explored');
            $table->timestamp('last_active_at')->nullable()->after('appointments_made');
            $table->timestamp('signup_started_at')->nullable()->after('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('demo_visits', function (Blueprint $table) {
            $table->dropColumn(['page_views', 'explored', 'appointments_made', 'last_active_at', 'signup_started_at']);
        });

        Schema::table('demo_invites', function (Blueprint $table) {
            $table->dropColumn(['views', 'last_viewed_at']);
        });
    }
};
