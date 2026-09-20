<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table): void {
            $table->string('customer_description', 255)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('repair_order_lines', function (Blueprint $table): void {
            $table->dropColumn('customer_description');
        });
    }
};
