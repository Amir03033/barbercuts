<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8: klanten terughalen (bericht na X weken zonder bezoek) en de wachtlijst bij volle dagen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            // Weken zonder bezoek waarna een klant een bericht krijgt; null = uit.
            $table->unsignedTinyInteger('winback_weeks')->nullable()->after('review_request_hours');
            $table->text('winback_message')->nullable()->after('winback_weeks');
            $table->boolean('waitlist_enabled')->default(true)->after('winback_message');
        });

        Schema::create('winback_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('status', 10)->default('sent');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['barbershop_id', 'sent_at']);
            $table->index(['customer_id', 'sent_at']);
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // null = geen voorkeur
            $table->foreignId('staff_member_id')->nullable()->constrained()->cascadeOnDelete();
            // De dag in de tijdzone van de zaak
            $table->date('date');
            $table->string('token', 40)->unique();
            // waiting · offered · booked · missed · cancelled · expired
            $table->string('status', 10)->default('waiting');
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['barbershop_id', 'date', 'status']);
            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('winback_messages');
        Schema::table('shop_settings', fn (Blueprint $table) => $table->dropColumn(['winback_weeks', 'winback_message', 'waitlist_enabled']));
    }
};
