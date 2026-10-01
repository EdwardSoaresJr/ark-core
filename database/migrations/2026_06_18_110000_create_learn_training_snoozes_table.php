<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_training_snoozes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('snoozed_at');
            $table->timestamp('snoozed_until');
            $table->timestamps();

            $table->unique('user_id', 'learn_snooze_user');
            $table->index('snoozed_until', 'learn_snooze_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_training_snoozes');
    }
};
