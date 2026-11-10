<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->unsignedInteger('no_show_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->timestamps();

            $table->unique(['barbershop_id', 'phone']);
            $table->unique(['barbershop_id', 'user_id']);
            $table->index(['barbershop_id', 'name']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('staff_member_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();

            // Snapshot bij boeken
            $table->string('service_name');
            $table->unsignedSmallInteger('duration');
            $table->unsignedInteger('price_cents');

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // ends_at + buffer_after; gebruikt voor overlap (§7.1)
            $table->dateTime('blocked_until');

            $table->string('status', 12)->default('scheduled');
            $table->string('source', 10)->default('online');
            $table->text('notes')->nullable();
            $table->string('manage_token', 40)->unique();

            $table->unsignedInteger('amount_paid_cents')->nullable();
            $table->unsignedInteger('tip_cents')->nullable();
            $table->string('payment_method', 10)->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->string('cancellation_reason')->nullable();

            // Aanbetaling, voorbereid (B6)
            $table->unsignedInteger('deposit_cents')->default(0);
            $table->string('deposit_status', 10)->default('none');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barbershop_id', 'starts_at']);
            $table->index(['staff_member_id', 'starts_at']);
            $table->index(['customer_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('customers');
    }
};
