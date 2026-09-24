<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_submission_intents', function (Blueprint $table) {
            $table->id();
            $table->uuid('intent_key')->unique('financial_submission_intent_key_unique');
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->string('operation', 16);
            $table->char('payload_fingerprint', 64);
            $table->foreignId('ledger_entry_id')->nullable()->constrained('repair_order_ledger_entries')->nullOnDelete();
            $table->timestamps();

            $table->index('repair_order_id', 'financial_submission_intent_ro_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_submission_intents');
    }
};
