<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: abonnementen, platforminstellingen, statistiek en 2FA voor de admin (docs/BOUWOPDRACHT.md §5, §7.7, §11).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price_cents');
            $table->string('interval', 10)->default('month');
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Het standaardplan: € 10 per maand, 14 dagen gratis (B10). De prijs is aan te passen in /admin.
        DB::table('plans')->insert([
            'name' => 'BarberCuts',
            'price_cents' => 1000,
            'interval' => 'month',
            'trial_days' => 14,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('barbershops', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mollie_subscription_id')->nullable()->unique();
            $table->string('status', 12)->default('pending');
            // Prijs die bij het starten is vastgelegd
            $table->unsignedInteger('price_cents');
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('past_due_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['barbershop_id', 'status']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('key', 60);
            $table->bigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['date', 'key']);
            $table->index('key');
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 12);
            $table->string('to');
            $table->string('type', 30);
            $table->unsignedInteger('cost_cents')->nullable();
            $table->string('provider_id')->nullable();
            $table->string('status', 12)->default('sent');
            $table->timestamps();

            $table->index(['channel', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });

        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('platform_daily_stats');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('subscriptions');

        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });

        Schema::dropIfExists('plans');
    }
};
