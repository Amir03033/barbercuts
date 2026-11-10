<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zaken, kappers, diensten en tijden (docs/BOUWOPDRACHT.md §5).
 * Bedragen in centen, tijden in UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barbershops', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 64)->default('Europe/Amsterdam');

            // Thema (B2)
            $table->string('theme_mode', 10)->default('light');
            $table->string('accent_color', 7)->default('#111827');
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();

            $table->boolean('is_public')->default(false);
            // Stap van de onboarding waar de eigenaar is gebleven; null = afgerond (§8)
            $table->unsignedTinyInteger('onboarding_step')->nullable();
            $table->timestamp('link_shared_at')->nullable();

            // Abonnement (§7.7). De foreign key naar plans volgt in fase 4.
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('free_until')->nullable();
            $table->string('free_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('mollie_customer_id')->nullable();
            $table->string('mollie_mandate_id')->nullable();
            $table->timestamps();

            $table->index('city');
        });

        Schema::create('shop_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);
            $table->timestamps();

            $table->unique(['barbershop_id', 'user_id']);
        });

        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#2563eb');
            $table->string('avatar_path')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Adres waar de uitnodiging naartoe is gestuurd (§8)
            $table->string('invited_email')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->index(['barbershop_id', 'is_active']);
            $table->unique(['barbershop_id', 'user_id']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration');
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('buffer_after')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Aanbetaling, voorbereid (B6)
            $table->string('deposit_type', 10)->default('none');
            $table->unsignedInteger('deposit_value')->default(0);
            $table->timestamps();

            $table->index(['barbershop_id', 'is_active']);
        });

        Schema::create('service_staff_member', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();

            $table->primary(['service_id', 'staff_member_id']);
        });

        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');

            $table->index(['barbershop_id', 'day_of_week']);
        });

        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');

            $table->index(['staff_member_id', 'day_of_week']);
        });

        Schema::create('time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('type', 15)->default('closed');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['barbershop_id', 'starts_at', 'ends_at']);
        });

        Schema::create('shop_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('slot_interval')->default(15);
            $table->unsignedInteger('min_notice')->default(60);
            $table->unsignedSmallInteger('max_days_ahead')->default(60);
            $table->unsignedInteger('customer_cancel_limit')->default(120);
            $table->unsignedInteger('customer_reschedule_limit')->default(120);
            $table->boolean('allow_guest_booking')->default(true);
            $table->boolean('allow_staff_choice')->default(true);
            $table->unsignedInteger('reminder_1_minutes')->default(1440);
            $table->unsignedInteger('reminder_2_minutes')->nullable();
            $table->json('reminder_channels');
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('shop_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barbershop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['barbershop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['activity_log', 'shop_images', 'shop_settings', 'time_off', 'working_hours', 'opening_hours',
            'service_staff_member', 'services', 'staff_members', 'shop_user', 'barbershops'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
