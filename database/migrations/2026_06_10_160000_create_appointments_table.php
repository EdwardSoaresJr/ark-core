<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('advisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('technician_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('repair_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('concern', 1000);
            $table->string('notes', 2000)->nullable();
            $table->string('status', 32)->default('scheduled');
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'status'], 'appt_starts_status');
            $table->index(['customer_id', 'starts_at'], 'appt_customer_starts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
