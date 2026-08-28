<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_decision_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_for');
            $table->string('notes', 500)->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'cleared_at'], 'cds_ro_cleared');
            $table->index(['customer_id', 'cleared_at'], 'cds_customer_cleared');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_decision_schedules');
    }
};
