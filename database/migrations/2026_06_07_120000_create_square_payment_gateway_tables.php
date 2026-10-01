<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->boolean('square_enabled')->default(false);
            $table->string('square_terminal_device_id', 128)->nullable();
            $table->boolean('square_terminal_enabled')->default(true);
            $table->boolean('square_keyed_enabled')->default(true);
            $table->boolean('square_portal_pay_enabled')->default(true);
            $table->boolean('square_email_pay_enabled')->default(true);
        });

        Schema::create('payment_gateway_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_document_id')->nullable()->constrained('estimate_documents')->nullOnDelete();
            $table->string('gateway', 32)->default('square');
            $table->string('capture_surface', 32);
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('idempotency_key', 64)->unique('pay_gw_attempt_idem_unique');
            $table->string('square_payment_id', 128)->nullable();
            $table->string('square_checkout_id', 128)->nullable();
            $table->string('status', 32);
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedBigInteger('processing_fee_cents')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('repair_order_ledger_entries')->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_access_token_id')->nullable();
            $table->timestamp('initiated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'status'], 'pay_gw_attempt_ro_status_idx');
            $table->index('square_payment_id', 'pay_gw_attempt_sq_pay_idx');
            $table->index('square_checkout_id', 'pay_gw_attempt_sq_chk_idx');
        });

        Schema::create('customer_document_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_document_id')->constrained('estimate_documents')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique('cust_doc_token_hash_unique');
            $table->string('scope', 32)->default('pay_invoice');
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['repair_order_id', 'scope'], 'cust_doc_token_ro_scope_idx');
        });

        Schema::table('payment_gateway_attempts', function (Blueprint $table) {
            $table->foreign('customer_access_token_id', 'pay_gw_attempt_token_fk')
                ->references('id')
                ->on('customer_document_access_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_attempts', function (Blueprint $table) {
            $table->dropForeign('pay_gw_attempt_token_fk');
        });

        Schema::dropIfExists('customer_document_access_tokens');
        Schema::dropIfExists('payment_gateway_attempts');

        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn([
                'square_enabled',
                'square_terminal_device_id',
                'square_terminal_enabled',
                'square_keyed_enabled',
                'square_portal_pay_enabled',
                'square_email_pay_enabled',
            ]);
        });
    }
};
