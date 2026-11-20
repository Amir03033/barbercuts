<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persoonlijke demo's van pitchers (een demo met de naam van de zaak van de kapper) en een regel per gestarte demo,
 * zodat de pitcher ziet wie zijn demo opende en of die zaak zich daarna aanmeldde. Plus live boekingen in een demo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 16)->unique();
            $table->string('shop_name', 80);
            $table->string('city', 80)->nullable();
            $table->string('accent_color', 7)->nullable();
            $table->unsignedInteger('starts')->default(0);
            $table->timestamp('last_started_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('demo_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pitcher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('demo_invite_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_up_barbershop_id')->nullable()->constrained('barbershops')->nullOnDelete();
            $table->timestamps();
            $table->index(['pitcher_id', 'created_at']);
        });

        Schema::table('barbershops', function (Blueprint $table) {
            $table->unsignedTinyInteger('demo_live_bookings')->default(0)->after('demo_referrer_id');
            $table->timestamp('demo_live_booking_at')->nullable()->after('demo_live_bookings');
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropColumn(['demo_live_bookings', 'demo_live_booking_at']);
        });

        Schema::dropIfExists('demo_visits');
        Schema::dropIfExists('demo_invites');
    }
};
