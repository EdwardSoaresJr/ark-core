<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('status', 32);
            $table->timestamp('scheduled_for');
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('delivery_mode', 16);
            $table->string('recipient_phone', 32)->nullable();
            $table->string('recipient_email')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_for'], 'som_status_scheduled_idx');
            $table->index(['repair_order_id', 'type', 'status'], 'som_ro_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_outbound_messages');
    }
};
