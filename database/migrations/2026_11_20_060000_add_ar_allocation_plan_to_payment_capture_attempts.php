<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_capture_attempts', function (Blueprint $table) {
            $table->json('allocation_plan')->nullable()->after('paid_on');
            $table->foreignId('customer_payment_receipt_id')
                ->nullable()
                ->after('ledger_entry_id')
                ->constrained('customer_payment_receipts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_capture_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_payment_receipt_id');
            $table->dropColumn('allocation_plan');
        });
    }
};
