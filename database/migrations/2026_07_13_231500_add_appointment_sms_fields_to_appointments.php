<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('confirmation_sms_sent_at')->nullable()->after('canceled_at');
            $table->boolean('reminder_day_before')->default(false)->after('confirmation_sms_sent_at');
            $table->unsignedTinyInteger('reminder_hours_before')->nullable()->after('reminder_day_before');
            $table->timestamp('reminder_day_before_sent_at')->nullable()->after('reminder_hours_before');
            $table->timestamp('reminder_hours_before_sent_at')->nullable()->after('reminder_day_before_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_sms_sent_at',
                'reminder_day_before',
                'reminder_hours_before',
                'reminder_day_before_sent_at',
                'reminder_hours_before_sent_at',
            ]);
        });
    }
};
