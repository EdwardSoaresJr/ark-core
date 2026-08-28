<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_order_status_definitions')) {
            return;
        }

        DB::table('repair_order_status_definitions')
            ->where('slug', 'draft')
            ->where('name', 'Needs Diagnosis')
            ->update(['name' => 'Draft']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_order_status_definitions')) {
            return;
        }

        DB::table('repair_order_status_definitions')
            ->where('slug', 'draft')
            ->where('name', 'Draft')
            ->update(['name' => 'Needs Diagnosis']);
    }
};
