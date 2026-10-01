<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 128);
            $table->string('aggregate_type', 128);
            $table->unsignedBigInteger('aggregate_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['aggregate_type', 'aggregate_id'], 'op_events_aggregate_idx');
            $table->index(['event_name', 'occurred_at'], 'op_events_name_time_idx');
            $table->index('actor_user_id', 'op_events_actor_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_events');
    }
};
