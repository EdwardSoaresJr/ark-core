<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_call_sid', 64)->nullable()->unique('call_sess_sid_unique');
            $table->string('direction', 16);
            $table->string('from_number', 32);
            $table->string('to_number', 32);
            $table->string('normalized_from', 32);
            $table->string('normalized_to', 32)->nullable();
            $table->string('status', 24);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['normalized_from', 'status'], 'call_sess_from_status_idx');
            $table->index('customer_id', 'call_sess_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
