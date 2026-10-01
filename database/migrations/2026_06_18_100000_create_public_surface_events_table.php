<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_surface_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 32);
            $table->string('session_id', 64);
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('attribution', 32)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['event', 'occurred_at'], 'pse_event_occurred_idx');
            $table->index(['session_id', 'event'], 'pse_session_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_surface_events');
    }
};
