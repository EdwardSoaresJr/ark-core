<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->string('customer_description_source', 16)->nullable()->after('customer_description');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table) {
            $table->dropColumn('customer_description_source');
        });
    }
};
