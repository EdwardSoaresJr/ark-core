<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_order_concerns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->string('summary');
            $table->text('notes')->nullable();
            $table->text('customer_states')->nullable();
            $table->text('verified_findings')->nullable();
            $table->text('dtcs_summary')->nullable();
            $table->text('recommendation')->nullable();
            $table->string('disposition', 32)->default('draft');
            $table->string('priority', 32)->default('normal');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['repair_order_id', 'priority'], 'ro_concerns_ro_priority_idx');
            $table->index(['repair_order_id', 'position'], 'ro_concerns_ro_position_idx');
            $table->index(['repair_order_id', 'disposition'], 'ro_concerns_ro_disposition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_order_concerns');
    }
};
