<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('today_recommendation_snoozes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('repair_order_id');
            $table->timestamp('snoozed_at');
            $table->timestamp('snoozed_until');
            $table->timestamps();

            $table->unique(['user_id', 'repair_order_id'], 'today_snooze_user_ro');
            $table->index(['user_id', 'snoozed_until'], 'today_snooze_user_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('today_recommendation_snoozes');
    }
};
