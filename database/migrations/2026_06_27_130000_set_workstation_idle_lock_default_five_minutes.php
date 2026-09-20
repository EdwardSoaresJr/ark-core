<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shop_settings', 'workstation_idle_lock_minutes')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('workstation_idle_lock_minutes')->default(5)->change();
        });

        DB::table('shop_settings')
            ->where('workstation_idle_lock_minutes', 15)
            ->update(['workstation_idle_lock_minutes' => 5]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('shop_settings', 'workstation_idle_lock_minutes')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('workstation_idle_lock_minutes')->default(15)->change();
        });
    }
};
