<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_arksms_customer_id')->nullable()->after('id');
            $table->unique('legacy_arksms_customer_id', 'customers_legacy_ark_cust_unique');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_arksms_vehicle_id')->nullable()->after('id');
            $table->unique('legacy_arksms_vehicle_id', 'vehicles_legacy_ark_vehicle_unique');
        });

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_arksms_repair_order_id')->nullable()->after('id');
            $table->unique('legacy_arksms_repair_order_id', 'ro_legacy_ark_ro_unique');
        });

        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_arksms_concern_id')->nullable()->after('id');
            $table->unique('legacy_arksms_concern_id', 'ro_concerns_legacy_ark_unique');
        });

        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_arksms_line_id')->nullable()->after('id');
            $table->unique('legacy_arksms_line_id', 'ro_lines_legacy_ark_unique');
        });

        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_arksms_invoice_id')->nullable()->after('id');
            $table->unique('legacy_arksms_invoice_id', 'estimate_docs_legacy_inv_unique');
        });
    }

    public function down(): void
    {
        Schema::table('estimate_documents', function (Blueprint $table) {
            $table->dropUnique('estimate_docs_legacy_inv_unique');
            $table->dropColumn('legacy_arksms_invoice_id');
        });

        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropUnique('ro_lines_legacy_ark_unique');
            $table->dropColumn('legacy_arksms_line_id');
        });

        Schema::table('repair_order_concerns', function (Blueprint $table) {
            $table->dropUnique('ro_concerns_legacy_ark_unique');
            $table->dropColumn('legacy_arksms_concern_id');
        });

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropUnique('ro_legacy_ark_ro_unique');
            $table->dropColumn('legacy_arksms_repair_order_id');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique('vehicles_legacy_ark_vehicle_unique');
            $table->dropColumn('legacy_arksms_vehicle_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_legacy_ark_cust_unique');
            $table->dropColumn('legacy_arksms_customer_id');
        });
    }
};
