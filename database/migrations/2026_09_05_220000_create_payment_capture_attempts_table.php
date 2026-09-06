<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_capture_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('repair_order_id')->constrained('repair_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 8)->default('USD');
            $table->string('context_kind', 32);
            $table->string('capture_method', 32);
            $table->string('status', 32);
            $table->string('idempotency_key', 191)->unique();
            $table->string('device_ref', 128)->nullable();
            $table->string('cloud_capture_id', 64)->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('provider_payment_id', 191)->nullable();
            $table->json('provider_refs')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('repair_order_ledger_entries')->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'status'], 'pay_cap_ro_status');
            $table->index(['repair_order_id', 'amount_cents', 'status'], 'pay_cap_ro_amt_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_capture_attempts');
    }
};
