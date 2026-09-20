<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('shop_settings', 'communications_channels')) {
            return;
        }

        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'telephony_call_flow')) {
                $table->json('communications_channels')->nullable()->after('telephony_call_flow');
            } else {
                $table->json('communications_channels')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table) {
            $table->dropColumn('communications_channels');
        });
    }
};
