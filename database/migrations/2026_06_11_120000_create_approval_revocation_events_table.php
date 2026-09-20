<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_revocation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('approval_event_id')->constrained('approval_events')->cascadeOnDelete();
            $table->string('source', 32);
            $table->string('revoked_by');
            $table->timestamp('revoked_at');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('reverted_concern_ids')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique('approval_event_id', 'approval_revocation_event_unique');
            $table->index('visit_id', 'approval_revocation_visit_idx');
            $table->index(['source', 'revoked_at'], 'approval_revocation_source_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_revocation_events');
    }
};
