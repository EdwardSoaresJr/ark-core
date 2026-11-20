<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_capture_attempts', function (Blueprint $table) {
            $table->uuid('submission_intent_key')->nullable()->after('idempotency_key');
            $table->index('submission_intent_key', 'pay_cap_submission_intent_idx');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('overpayment_disposition_default', 32)
                ->default('ask')
                ->after('store_credit_balance_cents');
        });
    }

    public function down(): void
    {
        Schema::table('payment_capture_attempts', function (Blueprint $table) {
            $table->dropIndex('pay_cap_submission_intent_idx');
            $table->dropColumn('submission_intent_key');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('overpayment_disposition_default');
        });
    }
};
