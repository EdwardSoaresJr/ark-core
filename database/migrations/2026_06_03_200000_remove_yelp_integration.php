<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('encounters') && Schema::hasColumn('encounters', 'source')) {
            DB::table('encounters')->where('source', 'yelp')->update(['source' => 'website']);
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'referral_source')) {
            DB::table('customers')->where('referral_source', 'yelp')->update(['referral_source' => null]);
        }

        if (Schema::hasColumn('shop_settings', 'yelp_zapier_webhook_secret')) {
            Schema::table('shop_settings', function (Blueprint $table): void {
                $table->dropColumn('yelp_zapier_webhook_secret');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shop_settings', function (Blueprint $table): void {
            $table->text('yelp_zapier_webhook_secret')->nullable()->after('messenger_app_secret');
        });
    }
};
