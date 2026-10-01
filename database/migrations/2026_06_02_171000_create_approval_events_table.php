<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->string('estimate_snapshot_reference')->nullable();
            $table->string('approval_type', 32);
            $table->unsignedInteger('approved_amount_cents')->nullable();
            $table->string('source', 32);
            $table->string('approved_by');
            $table->timestamp('approved_at');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('visit_id', 'approval_events_visit_idx');
            $table->index(['approval_type', 'approved_at'], 'approval_events_type_time_idx');
            $table->index(['source', 'approved_at'], 'approval_events_source_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_events');
    }
};
