<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('repair_orders', 'repair_order_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE repair_orders MODIFY repair_order_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('repair_orders', 'repair_order_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE repair_orders MODIFY repair_order_id BIGINT UNSIGNED NOT NULL');
    }
};
