<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisor_nudge_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_key', 64);
            $table->string('nudge_key', 64);
            $table->string('response', 16);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['user_id', 'entity_key', 'nudge_key', 'created_at'],
                'advisor_nudge_resp_user_entity_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_nudge_responses');
    }
};
