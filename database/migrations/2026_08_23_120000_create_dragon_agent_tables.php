<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dragon_agent_conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('model', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('dragon_agent_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dragon_agent_conversation_id')->constrained('dragon_agent_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('dragon_agent_traces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dragon_agent_conversation_id')->constrained('dragon_agent_conversations')->cascadeOnDelete();
            $table->unsignedInteger('round');
            $table->string('tool', 64);
            $table->json('arguments');
            $table->text('observation_summary');
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamps();
        });

        Schema::create('dragon_agent_memories', function (Blueprint $table): void {
            $table->id();
            $table->string('fact_key', 160);
            $table->text('fact_value');
            $table->string('taught_by', 80)->nullable();
            $table->string('provenance', 255)->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();
            $table->index(['fact_key', 'superseded_at']);
        });

        Schema::create('dragon_agent_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dragon_agent_conversation_id')->constrained('dragon_agent_conversations')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('model', 128)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('tool_calls')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedInteger('estimated_cost_cents')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dragon_agent_usages');
        Schema::dropIfExists('dragon_agent_memories');
        Schema::dropIfExists('dragon_agent_traces');
        Schema::dropIfExists('dragon_agent_messages');
        Schema::dropIfExists('dragon_agent_conversations');
    }
};
