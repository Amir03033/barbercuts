<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Pitchers brengen zaken aan: elke pitcher krijgt een eigen code (voor de aanmeldlink) en een zaak onthoudt wie haar aanbracht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 40)->nullable()->unique()->after('is_pitcher');
        });

        Schema::table('barbershops', function (Blueprint $table) {
            $table->foreignId('referred_by_user_id')->nullable()->after('demo_pitcher_id')->constrained('users')->nullOnDelete();
        });

        // Bestaande pitchers krijgen meteen een code.
        $taken = [];

        DB::table('users')->where('is_pitcher', true)->orderBy('id')->get(['id', 'name'])->each(function (object $pitcher) use (&$taken): void {
            $base = Str::slug(explode(' ', trim($pitcher->name))[0]) ?: 'pitcher';
            $code = $base;

            for ($number = 2; in_array($code, $taken, true); $number++) {
                $code = $base.$number;
            }

            $taken[] = $code;
            DB::table('users')->where('id', $pitcher->id)->update(['referral_code' => $code]);
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_code');
        });
    }
};
