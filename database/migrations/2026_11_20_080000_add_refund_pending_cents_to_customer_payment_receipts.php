<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payment_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_pending_cents')
                ->default(0)
                ->after('change_cents');
        });
    }

    public function down(): void
    {
        Schema::table('customer_payment_receipts', function (Blueprint $table) {
            $table->dropColumn('refund_pending_cents');
        });
    }
};
