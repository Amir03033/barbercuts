<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6: pitchers (mogen de demo starten) en demozaken (fictief, tijdelijk, buiten alle statistiek).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_pitcher')->default(false)->after('is_admin');
            $table->timestamp('last_demo_at')->nullable()->after('is_pitcher');
            $table->unsignedInteger('demo_count')->default(0)->after('last_demo_at');
        });

        Schema::table('barbershops', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('is_public');
            $table->timestamp('demo_expires_at')->nullable()->after('is_demo');
            $table->foreignId('demo_pitcher_id')->nullable()->after('demo_expires_at')->constrained('users')->nullOnDelete();

            $table->index(['is_demo', 'demo_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('demo_pitcher_id');
            $table->dropIndex(['is_demo', 'demo_expires_at']);
            $table->dropColumn(['is_demo', 'demo_expires_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_pitcher', 'last_demo_at', 'demo_count']);
        });
    }
};
