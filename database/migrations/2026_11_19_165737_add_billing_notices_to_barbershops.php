<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onthoudt welke e-mails over de proefperiode al verstuurd zijn, zodat een eigenaar elk bericht maar één keer krijgt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->json('billing_notices')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('barbershops', function (Blueprint $table) {
            $table->dropColumn('billing_notices');
        });
    }
};
