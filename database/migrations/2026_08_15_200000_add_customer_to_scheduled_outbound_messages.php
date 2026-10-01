<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_outbound_messages', function (Blueprint $table) {
            $table->dropForeign(['repair_order_id']);
        });

        Schema::table('scheduled_outbound_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('repair_order_id')->nullable()->change();
            $table->foreignId('customer_id')
                ->nullable()
                ->after('repair_order_id')
                ->constrained('customers')
                ->nullOnDelete();
            $table->index(['customer_id', 'type', 'status'], 'som_customer_type_status_idx');
        });

        Schema::table('scheduled_outbound_messages', function (Blueprint $table) {
            $table->foreign('repair_order_id')
                ->references('id')
                ->on('repair_orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_outbound_messages', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex('som_customer_type_status_idx');
            $table->dropColumn('customer_id');
            $table->dropForeign(['repair_order_id']);
        });

        Schema::table('scheduled_outbound_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('repair_order_id')->nullable(false)->change();
            $table->foreign('repair_order_id')
                ->references('id')
                ->on('repair_orders')
                ->cascadeOnDelete();
        });
    }
};
