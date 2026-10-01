<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings') || Schema::hasColumn('shop_settings', 'manual_payment_methods')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->json('manual_payment_methods')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shop_settings') || ! Schema::hasColumn('shop_settings', 'manual_payment_methods')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn('manual_payment_methods');
        });
    }
};
