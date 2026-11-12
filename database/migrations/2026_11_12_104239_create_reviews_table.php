<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fase 7: reviews na een bezoek, de score per zaak en kapper, en wat de kappersgids nodig heeft (plaats als slug).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            // Eén review per afspraak: alleen wie er geweest is, kan beoordelen.
            $table->foreignId('appointment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            // Reactie van de zaak
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            // Door de eigenaar gemeld bij het platform; alleen de admin kan verbergen.
            $table->timestamp('reported_at')->nullable();
            $table->string('report_reason')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->index(['barbershop_id', 'hidden_at', 'created_at']);
        });

        Schema::table('barbershops', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->nullable()->after('longitude');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
            // Gewogen score voor de ranglijst: weinig reviews trekken naar het gemiddelde.
            $table->decimal('rating_score', 5, 3)->default(0)->after('rating_count');
            $table->string('city_slug')->nullable()->after('city');

            $table->index(['city_slug', 'rating_score']);
            $table->index('rating_score');
        });

        Schema::table('staff_members', function (Blueprint $table) {
            $table->decimal('rating_avg', 3, 2)->nullable()->after('bio');
            $table->unsignedInteger('rating_count')->default(0)->after('rating_avg');
        });

        Schema::table('shop_settings', function (Blueprint $table) {
            // Uren na het bezoek waarop de klant om een review wordt gevraagd; null = uit.
            $table->unsignedSmallInteger('review_request_hours')->nullable()->default(2)->after('reminder_channels');
        });

        foreach (DB::table('barbershops')->whereNotNull('city')->get(['id', 'city']) as $shop) {
            DB::table('barbershops')->where('id', $shop->id)->update(['city_slug' => Str::slug($shop->city) ?: null]);
        }
    }

    public function down(): void
    {
        Schema::table('shop_settings', fn (Blueprint $table) => $table->dropColumn('review_request_hours'));
        Schema::table('staff_members', fn (Blueprint $table) => $table->dropColumn(['rating_avg', 'rating_count']));

        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropIndex(['city_slug', 'rating_score']);
            $table->dropIndex(['rating_score']);
            $table->dropColumn(['rating_avg', 'rating_count', 'rating_score', 'city_slug']);
        });

        Schema::dropIfExists('reviews');
    }
};
