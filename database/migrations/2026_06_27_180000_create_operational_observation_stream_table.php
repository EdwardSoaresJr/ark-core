<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_observation_stream', function (Blueprint $table) {
            $table->id();
            $table->string('observation_type', 64);
            $table->string('dedupe_key', 191)->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('repair_order_id')->nullable();
            $table->string('headline');
            $table->string('description', 500)->nullable();
            $table->string('source_event_name', 64)->nullable();
            $table->string('source_aggregate_type')->nullable();
            $table->unsignedBigInteger('source_aggregate_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['resolved_at', 'occurred_at'], 'obs_stream_active_idx');
            $table->index('customer_id', 'obs_stream_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_observation_stream');
    }
};
