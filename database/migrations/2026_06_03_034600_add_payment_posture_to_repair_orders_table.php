<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->string('payment_status', 32)->default('unpaid')->after('status');
            $table->timestamp('paid_at')->nullable()->after('payment_status');

            $table->index(['status', 'payment_status'], 'ro_status_payment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropIndex('ro_status_payment_idx');
            $table->dropColumn(['payment_status', 'paid_at']);
        });
    }
};
