<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('send_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('channel', 12)->nullable();
            $table->string('status', 10)->default('pending');
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at']);
            $table->index(['appointment_id', 'type']);
        });

        Schema::table('message_logs', function (Blueprint $table) {
            $table->string('error')->nullable()->after('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_preferences')->nullable();
        });

        // Push is nu een echt kanaal: zaken die nog de oude standaard hebben (alleen mail) krijgen push erbij.
        DB::table('shop_settings')->where('reminder_channels', '["mail"]')->update(['reminder_channels' => '["mail","push"]']);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_preferences'));
        Schema::table('message_logs', fn (Blueprint $table) => $table->dropColumn('error'));
        Schema::dropIfExists('appointment_notifications');
    }
};
