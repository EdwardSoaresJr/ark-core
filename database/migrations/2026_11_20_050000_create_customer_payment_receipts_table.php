<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->uuid('intent_key')->unique('customer_payment_receipt_intent_unique');
            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('allocated_cents')->default(0);
            $table->unsignedBigInteger('unapplied_cents')->default(0);
            $table->string('payment_method', 32);
            $table->string('reference', 128)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'voided_at'], 'customer_payment_receipt_cust_void_idx');
        });

        Schema::table('repair_order_ledger_entries', function (Blueprint $table) {
            $table->foreignId('customer_payment_receipt_id')
                ->nullable()
                ->after('financial_document_id')
                ->constrained('customer_payment_receipts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_payment_receipt_id');
        });

        Schema::dropIfExists('customer_payment_receipts');
    }
};
