<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repair_orders')) {
            return;
        }

        if (! Schema::hasColumn('repair_orders', 'public_id')) {
            Schema::table('repair_orders', function (Blueprint $table) {
                $table->uuid('public_id')->nullable()->unique('repair_orders_public_id_unique');
            });
        }

        DB::table('repair_orders')->whereNull('public_id')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('repair_orders')->where('id', $row->id)->update([
                    'public_id' => (string) Str::uuid(),
                ]);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('repair_orders') || ! Schema::hasColumn('repair_orders', 'public_id')) {
            return;
        }

        Schema::table('repair_orders', function (Blueprint $table) {
            $table->dropUnique('repair_orders_public_id_unique');
            $table->dropColumn('public_id');
        });
    }
};
