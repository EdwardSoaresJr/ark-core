<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payment_receipts', function (Blueprint $table) {
            $table->string('refund_evidence_kind', 32)->nullable()->after('refund_pending_cents');
            $table->string('refund_evidence_reference', 191)->nullable()->after('refund_evidence_kind');
            $table->timestamp('refund_confirmed_at')->nullable()->after('refund_evidence_reference');
            $table->foreignId('refund_confirmed_by')->nullable()->after('refund_confirmed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_payment_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_confirmed_by');
            $table->dropColumn([
                'refund_evidence_kind',
                'refund_evidence_reference',
                'refund_confirmed_at',
            ]);
        });
    }
};
