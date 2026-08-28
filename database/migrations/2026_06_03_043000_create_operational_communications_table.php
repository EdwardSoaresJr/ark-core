<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('communication_type', 48);
            $table->string('channel', 32);
            $table->string('direction', 16);
            $table->text('summary');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['repair_order_id', 'occurred_at'], 'op_comms_ro_occurred_idx');
            $table->index(['communication_type', 'occurred_at'], 'op_comms_type_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_communications');
    }
};
