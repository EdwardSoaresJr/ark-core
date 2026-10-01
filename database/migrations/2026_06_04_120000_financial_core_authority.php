<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->string('document_type', 32)->default('estimate')->after('repair_order_id');
            $table->timestamp('issued_at')->nullable()->after('generated_at');
        });

        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->dropUnique('estimate_docs_ro_unique');
        });

        DB::table('estimate_documents')->update(['document_type' => 'estimate']);

        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->dropUnique('estimate_docs_ro_num_unique');
            $table->unique(['repair_order_id', 'document_type'], 'estimate_docs_ro_type_unique');
        });

        Schema::create('repair_order_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_document_id')->nullable()->constrained('estimate_documents')->nullOnDelete();
            $table->string('entry_type', 32);
            $table->string('payment_method', 32)->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('reference', 128)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['repair_order_id', 'voided_at'], 'ro_ledger_ro_void_idx');
            $table->index(['customer_id', 'voided_at'], 'ro_ledger_cust_void_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('store_credit_balance_cents')->default(0)->after('customer_type');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('store_credit_balance_cents');
        });

        Schema::dropIfExists('repair_order_ledger_entries');

        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->dropUnique('estimate_docs_ro_type_unique');
            $table->dropColumn(['document_type', 'issued_at']);
            $table->unique(['repair_order_id', 'document_number'], 'estimate_docs_ro_num_unique');
            $table->unique('repair_order_id', 'estimate_docs_ro_unique');
        });
    }
};
