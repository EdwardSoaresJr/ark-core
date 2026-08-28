<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_order_concerns') || ! Schema::hasColumn('repair_order_concerns', 'disposition')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE repair_order_concerns ALTER COLUMN disposition SET DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_order_concerns') || ! Schema::hasColumn('repair_order_concerns', 'disposition')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE repair_order_concerns ALTER COLUMN disposition SET DEFAULT 'recommended'");
    }
};
