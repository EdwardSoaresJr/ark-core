<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('shop_settings', 'messenger_page_access_token')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->dropColumn('messenger_page_access_token');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('shop_settings', 'messenger_page_access_token')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->text('messenger_page_access_token')->nullable()->after('messenger_page_id');
        });
    }
};
