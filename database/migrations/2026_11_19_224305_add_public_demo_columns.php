<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Openbare demolink (/probeer/{code}): iedereen met de link krijgt zonder account een eigen demozaak.
 * Daarvoor maken we een tijdelijk demo-account aan, dat samen met de demozaak weer gewist wordt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_demo_visitor')->default(false)->after('is_pitcher')->index();
            $table->unsignedInteger('demo_link_starts')->default(0)->after('demo_count');
        });

        Schema::table('barbershops', function (Blueprint $table) {
            $table->foreignId('demo_referrer_id')->nullable()->after('demo_pitcher_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('demo_referrer_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_demo_visitor']);
            $table->dropColumn(['is_demo_visitor', 'demo_link_starts']);
        });
    }
};
