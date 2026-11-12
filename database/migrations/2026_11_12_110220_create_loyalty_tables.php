<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 9: de spaarkaart die een zaak zelf inricht, het saldo per klant en wat een afspraak eraan heeft gedaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_cards', function (Blueprint $table) {
            $table->id();
            // Eén kaart per zaak
            $table->foreignId('barbershop_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 60)->default('Spaarkaart');
            $table->unsignedTinyInteger('stamps_required')->default(10);
            // free = de afspraak is gratis · discount = een bedrag korting
            $table->string('reward_type', 10)->default('free');
            $table->unsignedInteger('reward_cents')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // Welke diensten een stempel opleveren; geen rijen = alle diensten.
        Schema::create('loyalty_card_service', function (Blueprint $table) {
            $table->foreignId('loyalty_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->primary(['loyalty_card_id', 'service_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('loyalty_stamps')->default(0)->after('no_show_count');
        });

        Schema::table('appointments', function (Blueprint $table) {
            // Wat deze afspraak met de spaarkaart deed, zodat "voltooien ongedaan maken" het precies terugdraait.
            $table->boolean('loyalty_stamp')->default(false)->after('completed_at');
            $table->unsignedTinyInteger('loyalty_redeemed_stamps')->default(0)->after('loyalty_stamp');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn(['loyalty_stamp', 'loyalty_redeemed_stamps']));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('loyalty_stamps'));
        Schema::dropIfExists('loyalty_card_service');
        Schema::dropIfExists('loyalty_cards');
    }
};
