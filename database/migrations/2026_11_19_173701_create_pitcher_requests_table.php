<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verzoeken van pitchers aan de beheerder: de proef van een zaak verlengen, een wens of een vraag doorgeven.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pitcher_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barbershop_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->text('answer')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pitcher_requests');
    }
};
