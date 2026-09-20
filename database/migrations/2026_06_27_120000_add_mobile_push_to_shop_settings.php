<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('shop_settings', 'mobile_push')) {
                $table->json('mobile_push')->nullable()->after('asterisk_voice');
            }

            if (! Schema::hasColumn('shop_settings', 'mobile_push_firebase_service_account')) {
                $table->text('mobile_push_firebase_service_account')->nullable()->after('mobile_push');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('shop_settings', 'mobile_push_firebase_service_account')) {
                $table->dropColumn('mobile_push_firebase_service_account');
            }

            if (Schema::hasColumn('shop_settings', 'mobile_push')) {
                $table->dropColumn('mobile_push');
            }
        });
    }
};
