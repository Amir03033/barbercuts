<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voorbereiding voor aanbetalingen (B6, §7.8): de payments-tabel (morph: abonnement of afspraak) en de
 * kolommen voor Mollie Connect per zaak. De koppeling zelf komt in een latere update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->morphs('payable');
            $table->string('mollie_payment_id')->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('barbershops', function (Blueprint $table) {
            $table->string('mollie_connect_organization_id')->nullable()->after('mollie_mandate_id');
            $table->text('mollie_connect_access_token')->nullable()->after('mollie_connect_organization_id');
            $table->text('mollie_connect_refresh_token')->nullable()->after('mollie_connect_access_token');
            $table->timestamp('mollie_connect_expires_at')->nullable()->after('mollie_connect_refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropColumn(['mollie_connect_organization_id', 'mollie_connect_access_token', 'mollie_connect_refresh_token', 'mollie_connect_expires_at']);
        });

        Schema::dropIfExists('payments');
    }
};
