<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dragon_nodes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 80);
            $table->boolean('enabled')->default(true);
            $table->json('capabilities')->nullable();
            $table->foreignId('dragon_service_token_id')
                ->nullable()
                ->constrained('dragon_service_tokens')
                ->nullOnDelete();
            $table->string('version', 40)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique('dragon_service_token_id', 'dragon_nodes_token_unique');
            $table->index(['enabled', 'last_seen_at'], 'dragon_nodes_presence_idx');
        });

        Schema::create('dragon_assist_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('task_type', 80);
            $table->string('status', 24);
            $table->json('payload_json');
            $table->foreignId('repair_order_id')->nullable()->constrained('repair_orders')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dragon_node_id')->nullable()->constrained('dragon_nodes')->nullOnDelete();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'task_type'], 'dragon_assist_status_task_idx');
            $table->index(['repair_order_id', 'status'], 'dragon_assist_ro_status_idx');
        });

        Schema::create('dragon_assist_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dragon_assist_request_id')
                ->constrained('dragon_assist_requests')
                ->cascadeOnDelete();
            $table->foreignId('dragon_node_id')->nullable()->constrained('dragon_nodes')->nullOnDelete();
            $table->json('result_json');
            $table->string('model_name', 80)->nullable();
            $table->string('model_version', 80)->nullable();
            $table->string('knowledge_version', 80)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->unique('dragon_assist_request_id', 'dragon_assist_results_req_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dragon_assist_results');
        Schema::dropIfExists('dragon_assist_requests');
        Schema::dropIfExists('dragon_nodes');
    }
};
