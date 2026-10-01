<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'workstation_idle_lock_minutes')) {
                $table->unsignedSmallInteger('workstation_idle_lock_minutes')->default(5);
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'workstation_idle_lock_minutes')) {
                $table->dropColumn('workstation_idle_lock_minutes');
            }
        });
    }
};
