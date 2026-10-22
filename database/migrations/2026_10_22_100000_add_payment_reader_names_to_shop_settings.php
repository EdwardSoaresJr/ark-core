<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shop_settings') || Schema::hasColumn('shop_settings', 'payment_reader_names')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->json('payment_reader_names')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shop_settings') || ! Schema::hasColumn('shop_settings', 'payment_reader_names')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn('payment_reader_names');
        });
    }
};
