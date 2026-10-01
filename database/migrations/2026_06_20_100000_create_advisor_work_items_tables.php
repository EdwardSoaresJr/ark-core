<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisor_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 1000);
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['created_by_user_id', 'completed_at', 'due_at'], 'adv_fu_user_done_due');
        });

        Schema::create('advisor_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 1000);
            $table->timestamp('due_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['created_by_user_id', 'completed_at', 'due_at'], 'adv_task_user_done_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_tasks');
        Schema::dropIfExists('advisor_follow_ups');
    }
};
