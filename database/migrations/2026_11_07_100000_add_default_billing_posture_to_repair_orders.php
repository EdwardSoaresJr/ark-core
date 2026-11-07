<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Null keeps a repair order on its customer's billing class, as every existing one is.
     */
    public function up(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->string('default_billing_posture', 24)->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropColumn('default_billing_posture');
        });
    }
};
