<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('draft');
            $table->text('concern_summary');
            $table->timestamps();

            $table->index(['customer_id', 'status'], 'ro_customer_status_idx');
            $table->index(['vehicle_id', 'status'], 'ro_vehicle_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_orders');
    }
};
